<div class="directory-tree">
    @if(isset($item['type']) && $item['type'] === 'directory')
        <div class="directory-item" data-path="{{ $item['path'] }}" data-name="{{ $item['name'] }}" data-loaded="false">
            <div class="flex items-center cursor-pointer hover:bg-gray-100 p-2 rounded group" onclick="toggleDirectory(this)">
                <svg class="w-6 h-6 mr-2 text-gray-400 group-hover:text-gray-600 transform transition-transform duration-200 expand-icon" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M7.293 4.293a1 1 0 011.414 0L14.414 10l-5.707 5.707a1 1 0 01-1.414-1.414L11.586 10 7.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
                <span class="font-medium text-gray-600">{{ $item['name'] }}</span>
            </div>
            <div class="sub-directory hidden pl-4 mt-1">
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
    @elseif(isset($item['type']) && $item['type'] === 'file')
        <div class="py-2 pl-6">
            <a href="javascript:void(0);"
               onclick="loadFile('{{ $item['path'] }}', '{{ $item['name'] }}')"
               data-path="{{ $item['path'] }}"
               data-filename="{{ $item['name'] }}"
               class="flex items-center text-gray-600 hover:text-blue-600 text-sm group rounded-lg p-2 transition-colors duration-200 hover:bg-blue-50">
                <svg class="w-6 h-6 mr-2 text-gray-400 group-hover:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                {{ $item['name'] }}
                <span class="ml-2 text-gray-400 text-xs">({{ $item['size'] }})</span>
            </a>
        </div>
    @endif
</div>
