<!DOCTYPE html>
<html>
<head>
    <title>日志内容 - {{ $filename }}</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-4">
            <h1 class="text-2xl font-bold">{{ $filename }}</h1>
            <a href="{{ route('finder.logs.index') }}"
               class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                返回列表
            </a>
        </div>

        <div class="bg-white shadow-md rounded-lg p-6">
            <div id="log-content" class="whitespace-pre-wrap"></div>
            <div id="loading" class="text-center py-4 hidden">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
            </div>
            <div id="load-more" class="text-center py-4 hidden">
                <button onclick="loadMore()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    加载更多
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentPage = 1;
        let hasMore = true;

        function loadContent(page = 1) {
            const loading = document.getElementById('loading');
            const loadMore = document.getElementById('load-more');
            loading.classList.remove('hidden');
            loadMore.classList.add('hidden');

            axios.get('{{ route('finder.logs.show') }}', {
                params: {
                    path: '{{ $path }}',
                    filename: '{{ $filename }}',
                    page: page
                }
            })
            .then(response => {
                const content = document.getElementById('log-content');
                if (page === 1) {
                    content.innerHTML = '';
                }

                response.data.content.forEach(line => {
                    content.innerHTML += `<div class="py-1">${line}</div>`;
                });

                hasMore = response.data.has_more;
                currentPage = page;

                loading.classList.add('hidden');
                if (hasMore) {
                    loadMore.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error loading log content:', error);
                loading.classList.add('hidden');
            });
        }

        function loadMore() {
            if (hasMore) {
                loadContent(currentPage + 1);
            }
        }

        // 初始加载
        loadContent();
    </script>
</body>
</html>
