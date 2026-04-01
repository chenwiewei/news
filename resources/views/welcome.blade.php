<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '视频生成系统')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<nav class="bg-white shadow-lg">
    <div class="max-w-6xl mx-auto px-4">
        <div class="flex justify-between">
            <div class="flex items-center pt-4">
                <a href="{{ route('video.index') }}" class="font-bold text-gray-700 hover:text-gray-900 text-xl">视频生成系统</a>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('video.index') }}" class="py-4 px-2 text-gray-700 hover:text-gray-900">项目列表</a>
                <a href="{{ route('video.create') }}" class="py-4 px-2 text-green-600 hover:text-green-800 font-bold">新建项目</a>
            </div>
        </div>
    </div>
</nav>

<main class="py-10">
    <div class="max-w-6xl mx-auto px-4">
        @yield('content')
    </div>
</main>
</body>
</html>
