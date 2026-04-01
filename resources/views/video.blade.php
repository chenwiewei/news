@extends('layouts.app')

@section('title', '创建视频项目')
@section('content')
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">创建新视频项目</h1>

        <form action="{{ route('video.store') }}" method="POST" enctype="multipart/form-data" id="projectForm">
            @csrf

            <div class="mb-6">
                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">项目标题</label>
                <input type="text" name="title" id="title" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="输入项目标题">
            </div>

            <div id="segmentsContainer">
            </div>

            <button type="button" onclick="addSegment()"
                    class="mt-4 w-full py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 transition duration-200">
                + 添加分段
            </button>

            <div class="mt-8 flex space-x-4">
                <button type="submit"
                        class="flex-1 py-3 bg-green-600 text-white font-bold rounded-lg hover:bg-green-700 transition duration-200">
                    生成视频
                </button>
                <a href="{{ route('video.index') }}"
                   class="px-6 py-3 bg-gray-400 text-white font-bold rounded-lg hover:bg-gray-500 transition duration-200 text-center">
                    取消
                </a>
            </div>
        </form>
    </div>

    <script>
        let segmentCount = 0;

        function addSegment() {
            const container = document.getElementById('segmentsContainer');
            const segmentId = segmentCount++;

            const segmentHtml = `
        <div class="segment-item bg-gray-50 rounded-lg p-6 mb-4 border-2 border-gray-200" id="segment-${segmentId}">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-700">分段 ${segmentId + 1}</h3>
                <button type="button" onclick="removeSegment(${segmentId})"
                        class="text-red-600 hover:text-red-800 font-bold">
                    删除
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">字幕文字 *</label>
                    <textarea name="segments[${segmentId}][text]" rows="3" required
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="输入这段的字幕文字"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">持续时间（秒） *</label>
                    <input type="number" name="segments[${segmentId}][duration]" min="1" max="60" value="5" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">背景图片（可选）</label>
                    <input type="file" name="segments[${segmentId}][image]" accept="image/*"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">支持 JPEG, PNG, GIF 格式，最大 10MB</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">背景视频（可选）</label>
                    <input type="file" name="segments[${segmentId}][video]" accept="video/*"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">支持 MP4, MOV, AVI 格式，最大 100MB</p>
                </div>
            </div>
        </div>
    `;

            container.insertAdjacentHTML('beforeend', segmentHtml);
            updateSegmentNumbers();
        }

        function removeSegment(segmentId) {
            const segment = document.getElementById(`segment-${segmentId}`);
            segment.remove();
            updateSegmentNumbers();
        }

        function updateSegmentNumbers() {
            const segments = document.querySelectorAll('.segment-item');
            segments.forEach((segment, index) => {
                const title = segment.querySelector('h3');
                title.textContent = `分段 ${index + 1}`;
            });
        }

        addSegment();
    </script>
@endsection
