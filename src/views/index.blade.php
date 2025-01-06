<!DOCTYPE html>
<html>
<head>
    <title>日志查看器</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        #dragbar {
            transition: background-color 0.2s;
        }
        #dragbar:hover {
            background-color: rgb(59, 130, 246);
        }
        .file-selected {
            background-color: rgb(239, 246, 255);
            border-radius: 0.5rem;
        }
        .directory-selected > div:first-child {
            background-color: rgb(239, 246, 255);
        }
        .directory-item .expand-icon {
            transition: transform 0.2s ease;
        }
        #log-content {
            word-wrap: break-word;
            word-break: break-all;
            white-space: pre-wrap;
        }
        #filename-display {
            word-wrap: break-word;
            word-break: break-all;
            max-width: 100%;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- 左侧目录树 -->
        <div id="sidebar" class="bg-white shadow-md fixed left-0 top-0 h-screen overflow-hidden flex flex-col" style="width: 256px;">
            <div class="p-4 border-b">
                <h2 class="text-xl font-bold mb-4">日志目录</h2>
                <div class="flex items-center justify-between mb-2">
                    <div class="relative flex-1">
                        <input type="text"
                               id="file-search"
                               class="w-full pl-8 pr-3 py-1 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="搜索文件..."
                               oninput="filterFiles(this.value)">
                        <svg class="w-4 h-4 absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <button onclick="refreshAllDirectories()"
                            class="ml-2 text-gray-400 hover:text-blue-500 transition-colors duration-200"
                            title="刷新所有目录">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto p-4">
                @foreach($directories as $key => $directory)
                    <div class="mb-4">
                        @include('finder::partials.directory-tree', ['item' => $directory])
                    </div>
                @endforeach
            </div>
        </div>

        <!-- 拖动条 -->
        <div id="dragbar" class="fixed top-0 w-1 h-screen bg-gray-300 hover:bg-blue-500 cursor-col-resize z-50 transition-colors duration-200"></div>

        <!-- 右侧内容区 -->
        <div class="flex-1 p-8 overflow-x-hidden" id="content-area">
            <div id="initial-message">
                <h1 class="text-2xl font-bold mb-4">选择日志文件查看内容</h1>
                <p class="text-gray-600">从左侧目录选择要查看的日志文件</p>
            </div>
            <div id="file-content" class="hidden">
                <div class="flex justify-between items-start mb-4 break-all">
                    <div class="flex-1">
                        <h1 class="text-2xl font-bold" id="filename-display"></h1>
                    </div>
                    <div class="flex items-center space-x-4 ml-4">
                        <div class="flex items-center space-x-2">
                            <input type="datetime-local"
                                   id="date-from"
                                   class="text-sm border rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   onchange="filterLogsByDate()">
                            <span class="text-gray-500">至</span>
                            <input type="datetime-local"
                                   id="date-to"
                                   class="text-sm border rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   onchange="filterLogsByDate()">
                            <button onclick="clearDateFilter()"
                                    class="text-gray-400 hover:text-blue-500 transition-colors duration-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="bg-white shadow-md rounded-lg p-6 relative">
                    <div class="absolute top-0 right-0 p-2 bg-white rounded-bl shadow">
                        <span id="line-count" class="text-sm text-gray-500"></span>
                    </div>
                    <div id="log-content"
                         class="font-mono text-sm h-[calc(100vh-200px)] overflow-y-auto break-all"
                         onscroll="handleScroll(this)">
                    </div>
                    <div id="loading" class="text-center py-4 hidden">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 配置版本检查
        const CONFIG_VERSION = '{{ md5(json_encode(config("finder.paths"))) }}';
        const cachedConfigVersion = localStorage.getItem('configVersion');

        // 如果配置发生变化，清除所有缓存
        if (cachedConfigVersion !== CONFIG_VERSION) {
            localStorage.removeItem('directoryCache');
            localStorage.removeItem('expandedDirs');
            localStorage.removeItem('selectedDir');
            localStorage.removeItem('selectedFile');
            localStorage.setItem('configVersion', CONFIG_VERSION);
        }

        // 状态管理
        const state = {
            sidebarWidth: parseInt(localStorage.getItem('sidebarWidth')) || 256,
            currentPath: localStorage.getItem('currentPath') || '',
            currentFilename: localStorage.getItem('currentFilename') || '',
            expandedDirs: new Set(JSON.parse(localStorage.getItem('expandedDirs')) || []),
            selectedFile: localStorage.getItem('selectedFile') || '',
            selectedDir: localStorage.getItem('selectedDir') || ''
        };

        // 目录缓存
        const directoryCache = new Map(JSON.parse(localStorage.getItem('directoryCache') || '[]'));

        // 定期清理缓存（24小时）
        setInterval(() => {
            const now = Date.now();
            for (const [path, data] of directoryCache) {
                if (now - data.timestamp > 24 * 60 * 60 * 1000) {
                    directoryCache.delete(path);
                }
            }
            saveDirectoryCache();
        }, 60 * 60 * 1000); // 每小时检查一次

        function saveDirectoryCache() {
            localStorage.setItem('directoryCache', JSON.stringify([...directoryCache]));
        }

        // 初始化侧边栏宽度
        document.getElementById('sidebar').style.width = state.sidebarWidth + 'px';
        document.getElementById('dragbar').style.left = state.sidebarWidth + 'px';
        document.getElementById('content-area').style.marginLeft = (state.sidebarWidth + 4) + 'px';

        // 拖动处理
        const dragbar = document.getElementById('dragbar');
        let isDragging = false;

        dragbar.addEventListener('mousedown', (e) => {
            isDragging = true;
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
        });

        document.addEventListener('mousemove', (e) => {
            if (isDragging) {
                const newWidth = Math.max(200, Math.min(600, e.clientX));
                document.getElementById('sidebar').style.width = newWidth + 'px';
                dragbar.style.left = newWidth + 'px';
                document.getElementById('content-area').style.marginLeft = (newWidth + 4) + 'px';
            }
        });

        document.addEventListener('mouseup', () => {
            if (isDragging) {
                isDragging = false;
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                // 保存宽度
                state.sidebarWidth = parseInt(document.getElementById('sidebar').style.width);
                localStorage.setItem('sidebarWidth', state.sidebarWidth);
            }
        });

        let currentPath = '';
        let currentFilename = '';
        let currentPage = 1;
        let hasMore = true;
        let isLoading = false;
        let totalLines = 0;
        let loadedLines = 0;

        function loadFile(path, filename) {
            // 解码路径并获取完整路径
            const decodedPath = atob(path);
            const relativePath = decodedPath.split('/storage/logs/')[1] || decodedPath;
            const fullPath = relativePath ? `${relativePath}/${filename}` : filename;

            // 移除之前的选中状态
            const prevSelected = document.querySelector('.file-selected');
            if (prevSelected) {
                prevSelected.classList.remove('file-selected', 'bg-blue-50');
            }

            // 添加新的选中状态
            const fileElement = document.querySelector(`[data-path="${path}"][data-filename="${filename}"]`);
            if (fileElement) {
                fileElement.classList.add('file-selected', 'bg-blue-50');
            }

            currentPath = path;
            currentFilename = filename;
            currentPage = 1;
            hasMore = true;
            loadedLines = 0;
            totalLines = 0;

            // 更新UI
            document.getElementById('initial-message').classList.add('hidden');
            document.getElementById('file-content').classList.remove('hidden');
            document.getElementById('filename-display').textContent = fullPath;
            document.getElementById('log-content').innerHTML = '';

            // 保存状态
            state.currentPath = path;
            state.currentFilename = filename;
            state.selectedFile = `${path}:${filename}`;
            localStorage.setItem('currentPath', path);
            localStorage.setItem('currentFilename', filename);
            localStorage.setItem('selectedFile', state.selectedFile);

            loadContent();
        }

        function handleScroll(element) {
            if (isLoading || !hasMore) return;

            const scrollPosition = element.scrollTop + element.clientHeight;
            const scrollHeight = element.scrollHeight;

            // 当滚动到距离底部 20% 时加载更多
            if ((scrollHeight - scrollPosition) / scrollHeight < 0.2) {
                loadContent(currentPage + 1);
            }
        }

        function loadContent(page = 1) {
            if (isLoading) return;
            isLoading = true;
            const loading = document.getElementById('loading');
            loading.classList.remove('hidden');

            axios.get('{{ route('finder.logs.show') }}', {
                params: {
                    path: currentPath,
                    filename: currentFilename,
                    page: page
                }
            })
            .then(response => {
                const content = document.getElementById('log-content');
                if (page === 1) {
                    content.innerHTML = '';
                    totalLines = response.data.total_lines || 0;
                    loadedLines = 0;
                }

                if (response.data && Array.isArray(response.data.content)) {
                    const fragment = document.createDocumentFragment();
                    response.data.content.forEach(line => {
                        loadedLines++;
                        const div = document.createElement('div');
                        div.className = 'mb-2 bg-white rounded-lg shadow';

                        if (line.timestamp) {
                            // 错误日志格式
                            const levelColors = {
                                'ERROR': 'bg-red-100 text-red-800',
                                'WARNING': 'bg-yellow-100 text-yellow-800',
                                'INFO': 'bg-blue-100 text-blue-800',
                                'DEBUG': 'bg-gray-100 text-gray-800'
                            };

                            const header = document.createElement('div');
                            header.className = `flex items-center justify-between p-3 cursor-pointer ${levelColors[line.level] || 'bg-gray-100'}`;
                            header.innerHTML = `
                                <div class="flex items-center space-x-3">
                                    <span class="expand-icon text-sm">▶</span>
                                    <span class="timestamp font-medium">${line.timestamp}</span>
                                    <span class="px-2 py-1 text-xs rounded-full ${levelColors[line.level] || 'bg-gray-200'}">${line.level}</span>
                                    <span class="text-sm">${line.type}</span>
                                </div>
                            `;

                            const content = document.createElement('div');
                            content.className = 'hidden p-3 bg-gray-50 border-t';
                            content.innerHTML = line.content.map(l => `<div class="py-1 font-mono text-sm">${l}</div>`).join('');

                            header.onclick = () => {
                                const icon = header.querySelector('.expand-icon');
                                if (content.classList.contains('hidden')) {
                                    content.classList.remove('hidden');
                                    icon.textContent = '▼';
                                } else {
                                    content.classList.add('hidden');
                                    icon.textContent = '▶';
                                }
                            };

                            div.appendChild(header);
                            div.appendChild(content);
                        } else {
                            // 普通日志行
                            div.className = 'py-1 px-3 hover:bg-gray-100 font-mono text-sm';
                            div.innerHTML = line.content[0];
                        }
                        fragment.appendChild(div);
                    });
                    content.appendChild(fragment);

                    hasMore = response.data.has_more;
                    currentPage = page;
                    document.getElementById('line-count').textContent =
                        `已加载 ${loadedLines} / ${totalLines} 行`;

                    loading.classList.add('hidden');
                } else {
                    console.error('Invalid response format:', response.data);
                    loading.classList.add('hidden');
                }
                isLoading = false;

                // 如果内容高度不足以滚动，且还有更多内容，则继续加载
                if (content.scrollHeight <= content.clientHeight && hasMore) {
                    loadContent(currentPage + 1);
                }
            })
            .catch(error => {
                console.error('Error loading log content:', error.response?.data || error.message);
                loading.classList.add('hidden');
                isLoading = false;
                alert('加载日志内容失败，请检查控制台获取详细信息');
            });
        }

        function toggleDirectory(element) {
            const directoryItem = element.closest('.directory-item');
            const subDirectory = directoryItem.querySelector('.sub-directory');
            const expandIcon = element.querySelector('.expand-icon');
            const loading = subDirectory.querySelector('.directory-loading');
            const isLoaded = directoryItem.dataset.loaded === 'true';
            const path = directoryItem.dataset.path;
            const name = directoryItem.dataset.name;

            // 移除之前的选中状态
            const prevSelected = document.querySelector('.directory-selected');
            if (prevSelected) {
                prevSelected.classList.remove('directory-selected');
            }

            // 添加新的选中状态
            directoryItem.classList.add('directory-selected');

            // 保存选中状态
            state.selectedDir = `${path}:${name}`;
            localStorage.setItem('selectedDir', state.selectedDir);

            // 保存展开状态
            if (subDirectory.classList.contains('hidden')) {
                state.expandedDirs.add(path);
            } else {
                state.expandedDirs.delete(path);
            }
            localStorage.setItem('expandedDirs', JSON.stringify([...state.expandedDirs]));

            if (subDirectory.classList.contains('hidden')) {
                expandIcon.style.transform = 'rotate(90deg)';
                subDirectory.classList.remove('hidden');

                if (!isLoaded) {
                    loading.classList.remove('hidden');
                    loadSubDirectory(path, subDirectory);
                }
            } else {
                expandIcon.style.transform = 'rotate(0deg)';
                subDirectory.classList.add('hidden');
            }
        }

        function loadSubDirectory(path, container, forceRefresh = false) {
            const directoryItem = container.closest('.directory-item');
            const loading = container.querySelector('.directory-loading');

            // 检查缓存
            if (!forceRefresh && directoryCache.has(path)) {
                const cachedData = directoryCache.get(path);
                renderDirectoryContent(cachedData.data, container, directoryItem);
                return;
            }

            axios.get('{{ route('finder.logs.directory') }}', {
                params: { path: path }
            })
            .then(response => {
                const data = response.data;
                // 更新缓存
                directoryCache.set(path, {
                    data: data,
                    timestamp: Date.now()
                });
                saveDirectoryCache();

                renderDirectoryContent(data, container, directoryItem);
            })
            .catch(error => {
                console.error('Error loading directory:', error);
                container.innerHTML = '<div class="text-red-500 p-2">加载失败</div>';
                loading.classList.add('hidden');
            });
        }

        function renderDirectoryContent(data, container, directoryItem) {
            let html = `
                <div class="directory-loading hidden">
                    <div class="flex items-center p-2 text-gray-400">
                        <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-sm">加载中...</span>
                    </div>
                </div>
            `;

            // 添加子目录
            if (data.directories) {
                data.directories.forEach(dir => {
                    html += `
                        <div class="directory-item"
                             data-path="${dir.path}"
                             data-name="${dir.name}"
                             data-loaded="false">
                            <div class="flex items-center justify-between hover:bg-gray-100 p-2 rounded group transition-all duration-200">
                                <div class="flex items-center cursor-pointer" onclick="toggleDirectory(this)">
                                    <svg class="w-6 h-6 mr-2 text-gray-400 group-hover:text-gray-600 transform transition-transform duration-200 expand-icon" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.293 4.293a1 1 0 011.414 0L14.414 10l-5.707 5.707a1 1 0 01-1.414-1.414L11.586 10 7.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="font-medium text-gray-600">${dir.name}</span>
                                </div>
                                <button onclick="refreshDirectory(this, event)"
                                        data-path="${dir.path}"
                                        class="opacity-0 group-hover:opacity-100 text-xs text-gray-400 hover:text-blue-500 flex items-center transition-opacity duration-200">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>
                            </div>
                            <div class="sub-directory hidden pl-4 mt-1 transition-all duration-200">
                                <div class="directory-loading hidden">
                                    <div class="flex items-center p-2 text-gray-400">
                                        <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="text-sm">加载中...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }

            // 添加文件
            if (data.files) {
                data.files.forEach(file => {
                    html += `
                        <div class="py-2 pl-6">
                            <a href="javascript:void(0);"
                               onclick="loadFile('${file.path}', '${file.name}')"
                               data-path="${file.path}"
                               data-filename="${file.name}"
                               class="flex items-center text-gray-600 hover:text-blue-600 text-sm group rounded-lg p-2 transition-colors duration-200 hover:bg-blue-50">
                                <svg class="w-6 h-6 mr-2 text-gray-400 group-hover:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                ${file.name}
                                <span class="ml-2 text-gray-400 text-xs">(${file.size})</span>
                            </a>
                        </div>
                    `;
                });
            }

            container.innerHTML = html;
            directoryItem.dataset.loaded = 'true';
            container.querySelector('.directory-loading').classList.add('hidden');

            // 恢复选中状态
            if (state.selectedDir) {
                const [selectedPath, selectedName] = state.selectedDir.split(':');
                const dirElement = container.querySelector(`[data-path="${selectedPath}"][data-name="${selectedName}"]`);
                if (dirElement) {
                    dirElement.classList.add('directory-selected');
                }
            }
        }

        function refreshDirectory(button, event) {
            // 阻止事件冒泡，避免触发目录展开
            event.stopPropagation();

            const path = button.dataset.path;
            const directoryItem = button.closest('.directory-item');
            const container = directoryItem.querySelector('.sub-directory');

            // 清除缓存
            directoryCache.delete(path);
            saveDirectoryCache();

            // 重新加载
            container.querySelector('.directory-loading').classList.remove('hidden');
            loadSubDirectory(path, container, true);

            // 显示刷新成功提示
            showToast('目录已刷新');
        }

        // 刷新所有目录
        function refreshAllDirectories() {
            // 清除所有缓存
            directoryCache.clear();
            saveDirectoryCache();

            // 保存当前展开和选中状态
            const expandedDirs = state.expandedDirs;
            const selectedDir = state.selectedDir;
            const selectedFile = state.selectedFile;

            // 重新加载所有已展开的目录
            expandedDirs.forEach(path => {
                const dirElement = document.querySelector(`[data-path="${path}"]`);
                if (dirElement) {
                    const container = dirElement.querySelector('.sub-directory');
                    if (container && !container.classList.contains('hidden')) {
                        loadSubDirectory(path, container, true);
                    }
                }
            });

            // 显示刷新成功提示
            showToast('目录已刷新');
        }

        // 显示提示消息
        function showToast(message) {
            const toast = document.createElement('div');
            toast.className = 'fixed bottom-4 right-4 bg-gray-800 text-white px-4 py-2 rounded-lg shadow-lg transform transition-all duration-300 opacity-0';
            toast.textContent = message;
            document.body.appendChild(toast);

            // 显示动画
            setTimeout(() => toast.classList.add('opacity-100'), 100);

            // 3秒后隐藏
            setTimeout(() => {
                toast.classList.remove('opacity-100');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // 页面加载时恢复状态
        document.addEventListener('DOMContentLoaded', () => {
            // 恢复展开的目录
            state.expandedDirs.forEach(path => {
                const dirElement = document.querySelector(`[data-path="${path}"]`);
                if (dirElement) {
                    toggleDirectory(dirElement.querySelector('[onclick]'));
                }
            });

            // 恢复选中的文件
            if (state.currentPath && state.currentFilename) {
                loadFile(state.currentPath, state.currentFilename);
            }

            // 恢复选中的目录
            if (state.selectedDir) {
                const [path, name] = state.selectedDir.split(':');
                const dirElement = document.querySelector(`[data-path="${path}"][data-name="${name}"]`);
                if (dirElement) {
                    dirElement.classList.add('directory-selected');
                }
            }
        });

        // 文件搜索函数
        function filterFiles(searchText) {
            const searchLower = searchText.toLowerCase();
            const fileElements = document.querySelectorAll('[data-filename]');
            const directoryElements = document.querySelectorAll('.directory-item');

            fileElements.forEach(el => {
                const filename = el.dataset.filename.toLowerCase();
                const parentDir = el.closest('.sub-directory');
                if (filename.includes(searchLower)) {
                    el.style.display = '';
                    // 确保父目录可见
                    if (parentDir && parentDir.classList.contains('hidden')) {
                        const dirItem = parentDir.closest('.directory-item');
                        if (dirItem && !dirItem.dataset.loaded) {
                            toggleDirectory(dirItem.querySelector('[onclick]'));
                        }
                    }
                } else {
                    el.style.display = 'none';
                }
            });

            // 处理空目录的显示
            directoryElements.forEach(dir => {
                const visibleFiles = dir.querySelectorAll('[data-filename]:not([style*="display: none"])');
                if (visibleFiles.length === 0 && searchText) {
                    dir.style.display = 'none';
                } else {
                    dir.style.display = '';
                }
            });
        }

        // 日期过滤函数
        function filterLogsByDate() {
            const fromDate = document.getElementById('date-from').value;
            const toDate = document.getElementById('date-to').value;

            if (!fromDate && !toDate) return;

            const from = fromDate ? new Date(fromDate) : null;
            const to = toDate ? new Date(toDate) : null;

            const logEntries = document.querySelectorAll('#log-content > div');
            logEntries.forEach(entry => {
                const timestamp = entry.querySelector('.timestamp')?.textContent;
                if (timestamp) {
                    const entryDate = new Date(timestamp);
                    const showEntry = (!from || entryDate >= from) && (!to || entryDate <= to);
                    entry.style.display = showEntry ? '' : 'none';
                }
            });
        }

        // 清除日期过滤
        function clearDateFilter() {
            document.getElementById('date-from').value = '';
            document.getElementById('date-to').value = '';

            const logEntries = document.querySelectorAll('#log-content > div');
            logEntries.forEach(entry => {
                entry.style.display = '';
            });
        }
    </script>
</body>
</html>
