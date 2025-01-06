<?php

namespace Feelri\Finder\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;

class LogController extends Controller
{
    protected $basePaths;

    public function __construct()
    {
        $this->basePaths = $this->normalizePaths(config('finder.paths', ['logs' => storage_path('logs')]));
    }

    protected function normalizePaths($paths)
    {
        $normalized = [];
        foreach ($paths as $key => $path) {
            $absolutePath = realpath($path);
            if ($absolutePath !== false) {
                $normalized[$key] = rtrim($absolutePath, DIRECTORY_SEPARATOR);
            }
        }
        return $normalized;
    }

    public function index(Request $request)
    {
        $directories = [];
        foreach ($this->basePaths as $key => $path) {
            $directories[$key] = [
                'path' => base64_encode($path),
                'name' => $key,
                'type' => 'directory'
            ];
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($directories);
        }

        return view('finder::index', [
            'directories' => $directories
        ]);
    }

    public function show(Request $request)
    {
        $path = base64_decode($request->input('path'));
        $filename = $request->input('filename');

        if (!$path || !is_dir($path)) {
            return response()->json(['error' => '日志目录不存在'], 404);
        }

        $fullPath = $path . DIRECTORY_SEPARATOR . $filename;

        if (!is_file($fullPath)) {
            return response()->json(['error' => '日志文件不存在'], 404);
        }

        $realPath = realpath($fullPath);
        if (!$realPath || !$this->isPathAllowed($realPath)) {
            return response()->json(['error' => '无权访问该文件'], 403);
        }

        if ($request->ajax() || $request->wantsJson()) {
            $page = $request->get('page', 1);
            $perPage = config('finder.per_page', 1000);
            $content = $this->getFileContent($fullPath, $page, $perPage);

            return response()->json([
                'content' => $content['lines'],
                'has_more' => $content['has_more'],
                'total_pages' => $content['total_pages']
            ]);
        }

        return view('finder::show', [
            'path' => $path,
            'filename' => $filename
        ]);
    }

    public function getSubDirectory(Request $request)
    {
        $path = base64_decode($request->input('path'));

        if (!$path || !is_dir($path)) {
            return response()->json(['error' => '目录不存在'], 404);
        }

        $realPath = realpath($path);
        if (!$this->isPathAllowed($realPath)) {
            return response()->json(['error' => '无权访问该目录'], 403);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($this->scanDirectory($path));
        }

        $data = $this->scanDirectory($path);
        return view('finder::partials.directory-tree', [
            'item' => [
                'path' => base64_encode($path),
                'name' => basename($path),
                'type' => 'directory',
                'children' => $data
            ]
        ]);
    }

    protected function isPathAllowed($path)
    {
        foreach ($this->basePaths as $basePath) {
            $realBasePath = realpath($basePath);
            if ($realBasePath === false) {
                continue;
            }
            if (strpos($path, $realBasePath) === 0) {
                return true;
            }
        }
        return false;
    }

    protected function scanDirectory($path)
    {
        $result = [];
        try {
            $realPath = realpath($path);
            if (!$realPath || !is_dir($realPath)) {
                return $result;
            }

            $path = $realPath;
            $result['current_path'] = base64_encode($path);

            $directories = File::directories($path);
            $result['directories'] = [];
            foreach ($directories as $directory) {
                $dirName = basename($directory);
                $result['directories'][] = [
                    'name' => $dirName,
                    'path' => base64_encode($directory),
                    'type' => 'directory'
                ];
            }

            $items = File::files($path);
            $result['files'] = [];
            foreach ($items as $item) {
                if (pathinfo($item, PATHINFO_EXTENSION) === 'log') {
                    $result['files'][] = [
                        'type' => 'file',
                        'name' => $item->getFilename(),
                        'path' => base64_encode($path),
                        'size' => $this->formatSize($item->getSize()),
                        'modified' => date('Y-m-d H:i:s', $item->getMTime())
                    ];
                }
            }
        } catch (\Exception $e) {
            \Log::error("Error scanning directory: {$path}", ['error' => $e->getMessage()]);
        }

        return $result;
    }

    protected function getFileContent($path, $page, $perPage)
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        $totalPages = ceil($totalLines / $perPage);

        $start = ($page - 1) * $perPage;
        $end = $start + $perPage;

        $lines = [];
        $currentError = [];
        $errorPattern = '/^\[([^\]]+)\]\s+((?:production|local|testing|development)\.)?([A-Z]+):/';

        if ($page > 1) {
            $lookBehind = 50;
            $lookBehindStart = max(0, $start - $lookBehind);
            $file->seek($lookBehindStart);

            while ($file->key() < $start) {
                $line = $file->current();
                if ($line !== false && preg_match($errorPattern, $line, $matches)) {
                    $currentError = [
                        'timestamp' => $matches[1] ?? '',
                        'env' => rtrim($matches[2] ?? '', '.'),
                        'level' => $matches[3] ?? 'UNKNOWN',
                        'content' => []
                    ];
                } elseif (!empty($currentError)) {
                    $currentError['content'][] = $line;
                }
                $file->next();
            }
        }

        $file->seek($start);
        $currentLine = $start;
        $addedLines = 0;

        while (!$file->eof() && $currentLine < $end) {
            $line = $file->current();
            if ($line !== false) {
                if (preg_match($errorPattern, $line, $matches)) {
                    if (!empty($currentError)) {
                        if ($currentLine >= $start) {
                            $lines[] = $currentError;
                            $addedLines++;
                        }
                    }
                    $currentError = [
                        'timestamp' => $matches[1] ?? '',
                        'env' => rtrim($matches[2] ?? '', '.'),
                        'level' => $matches[3] ?? 'UNKNOWN',
                        'content' => [htmlspecialchars($line)]
                    ];
                } elseif (!empty($currentError)) {
                    $currentError['content'][] = htmlspecialchars($line);
                } else {
                    $lines[] = ['content' => [htmlspecialchars($line)]];
                    $addedLines++;
                }
            }
            $file->next();
            $currentLine++;

            if ($addedLines >= $perPage && !empty($currentError)) {
                while (!$file->eof()) {
                    $nextLine = $file->current();
                    if ($nextLine !== false && preg_match($errorPattern, $nextLine)) {
                        break;
                    }
                    $currentError['content'][] = htmlspecialchars($nextLine);
                    $file->next();
                }
                break;
            }
        }

        if (!empty($currentError)) {
            $lines[] = $currentError;
        }

        return [
            'lines' => $lines,
            'has_more' => $file->key() < $totalLines,
            'total_pages' => $totalPages,
            'total_lines' => $totalLines
        ];
    }

    private function formatSize($size)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
}
