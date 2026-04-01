@extends('layouts.app')

@section('title', '视频项目列表')
@section('content')
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">视频项目列表</h1>

        @if(count($projects) > 0)
            <div class="space-y-4">
                @foreach($projects as $project)
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-lg transition duration-200">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h3 class="text-xl font-bold text-gray-800">{{ $project['name'] }}</h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    创建时间：{{ date('Y-m-d H:i:s', $project['created_at']) }}
                                </p>
                                <p class="text-sm text-gray-600">
                                    路径：{{ $project['path'] }}
                                </p>
                            </div>

                            <div class="flex items-center space-x-4">
                                @if($project['video_path'])
                                    <span class="px-3 py-1 rounded-full text-sm font-bold bg-green-100 text-green-800">
                                    已完成
                                </span>
                                    <a href="{{ route('video.download', base64_encode($project['path'])) }}"
                                       class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-bold">
                                        下载视频
                                    </a>
                                @else
                                    <span class="px-3 py-1 rounded-full text-sm font-bold bg-yellow-100 text-yellow-800">
                                    处理中
                                </span>
                                @endif

                                <a href="{{ route('video.generate', base64_encode($project['path'])) }}"
                                   class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-bold">
                                    重新生成
                                </a>
                            </div>
                        </div>

                        @if($project['video_path'])
                            <div class="mt-4">
                                <video controls class="w-full max-w-2xl rounded-lg">
                                    <source src="{{ asset('storage/temp/') }}/../{{ $project['video_path'] }}" type="video/mp4">
                                    您的浏览器不支持视频标签。
                                </video>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12">
                <p class="text-gray-600 text-lg">暂无项目</p>
                <a href="{{ route('video.create') }}"
                   class="mt-4 inline-block px-6 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700">
                    创建第一个项目
                </a>
            </div>
        @endif
    </div>
@endsection
