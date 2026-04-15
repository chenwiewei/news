@extends('layouts.app')

@section('title', '生成视频')
@section('content')
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">生成视频</h1>

        <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-700 mb-2">项目：{{ $project->title }}</h2>
            <p class="text-sm text-gray-600">保存路径：{{ $project->base_dir }}</p>
        </div>

        <div class="mb-6">
            <h3 class="text-lg font-bold text-gray-700 mb-3">生成状态</h3>

            @if($project->status === 'processing')
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <svg class="animate-spin h-8 w-8 text-blue-600 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-blue-800 font-bold text-lg">正在生成视频，请稍候...</span>
                    </div>
                    <p class="text-blue-700 mt-2">这可能需要几分钟时间。</p>
                </div>

                <meta http-equiv="refresh" content="5">

            @elseif($project->status === 'completed')
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <p class="text-green-800 font-bold text-lg">✓ 视频生成成功！</p>

                    @php
                        $videoFile = $project->base_dir . '/' . $project->title . '.mp4';
                    @endphp

                    @if(file_exists($videoFile))
                        <div class="mt-4">
                            <video controls class="w-full max-w-2xl rounded-lg">
                                <source src="{{ asset('storage/temp/') }}/../{{ $videoFile }}" type="video/mp4">
                                您的浏览器不支持视频标签。
                            </video>
                        </div>

                        <div class="mt-4">
                            <a href="{{ route('video.download', ['encodedPath' => $project->base_dir]) }}"
                               class="inline-block px-6 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700">
                                下载视频
                            </a>
                        </div>
                    @endif
                </div>

            @elseif($project->status === 'failed')
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-800 font-bold text-lg">✗ 视频生成失败</p>
                    <p class="text-red-700 mt-2">错误信息：{{ $project->error_message }}</p>
                </div>

            @else
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-yellow-800 font-bold">等待生成...</p>
                </div>
            @endif
        </div>

        <div class="flex space-x-4">
            <a href="{{ route('video.index') }}"
               class="px-6 py-3 bg-gray-400 text-white font-bold rounded-lg hover:bg-gray-500">
                返回列表
            </a>

            @if($project->status !== 'processing')
                <a href="{{ route('video.create') }}"
                   class="px-6 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700">
                    创建新项目
                </a>
            @endif
        </div>
    </div>
@endsection
