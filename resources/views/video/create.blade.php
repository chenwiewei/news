@extends('layouts.app')

@section('title', '创建视频项目')
@section('content')
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">创建新视频项目</h1>

        @if($errors->any())
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <h3 class="text-red-800 font-bold mb-2">提交失败，请检查以下错误：</h3>
                <ul class="list-disc list-inside text-red-700">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('video.store') }}" method="POST" enctype="multipart/form-data" id="projectForm">
            @csrf

            <div class="mb-6">
                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">项目标题</label>
                <input type="text" name="title" id="title" required value="{{ old('title') }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('title') border-red-500 @enderror"
                       placeholder="输入项目标题">
                @error('title')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
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

        function estimateDuration(text) {
            if (!text || text.trim() === '') {
                return 0;
            }
            const charCount = text.length;
            const chineseChars = (text.match(/[\u4e00-\u9fa5]/g) || []).length;
            const otherChars = charCount - chineseChars;
            const duration = (chineseChars * 0.3) + (otherChars * 0.15);
            return Math.max(2, Math.ceil(duration));
        }

        function updateDurationInput(textarea, durationInput) {
            const estimatedTime = estimateDuration(textarea.value);
            if (estimatedTime > 0) {
                durationInput.value = `约 ${estimatedTime} 秒`;
            } else {
                durationInput.value = '计算中...';
            }
        }

        function renumberSegments() {
            const segments = document.querySelectorAll('.segment-item');
            segments.forEach((segment, newIndex) => {
                segment.id = `segment-${newIndex}`;

                const title = segment.querySelector('h3');
                title.textContent = `分段 ${newIndex + 1}`;

                const deleteBtn = segment.querySelector('button[onclick^="removeSegment"]');
                if (deleteBtn) {
                    deleteBtn.setAttribute('onclick', `removeSegment(${newIndex})`);
                }

                // 处理 textarea（需要移除事件监听器）
                const textarea = segment.querySelector('textarea[name^="segments["]');
                if (textarea) {
                    textarea.name = `segments[${newIndex}][text]`;

                    // 只克隆 textarea，不影响其他元素
                    const newTextarea = textarea.cloneNode(true);
                    textarea.parentNode.replaceChild(newTextarea, textarea);

                    // 重新绑定事件
                    const durationInput = segment.querySelector(`input[id^="duration-"]`);
                    if (durationInput) {
                        newTextarea.addEventListener('input', function() {
                            updateDurationInput(this, durationInput);
                        });

                        // 立即更新一次
                        updateDurationInput(newTextarea, durationInput);
                    }
                }

                // 处理 duration input
                const durationInput = segment.querySelector('input[name^="segments["][id^="duration-"]');
                if (durationInput) {
                    durationInput.name = `segments[${newIndex}][duration]`;
                    durationInput.id = `duration-${newIndex}`;
                }

                // 处理 image input（不要克隆，只改 name）
                const imageInput = segment.querySelector('input[type="file"][accept="image/*"]');
                if (imageInput) {
                    imageInput.name = `segments[${newIndex}][image]`;
                }

                // 处理 video input（不要克隆，只改 name）
                const videoInput = segment.querySelector('input[type="file"][accept="video/*"]');
                if (videoInput) {
                    videoInput.name = `segments[${newIndex}][video]`;
                }
            });

            segmentCount = segments.length;
        }

        function addSegment() {
            //const container = document.getElementById('segmentsContainer');
            //const segmentId = segmentCount++;
            const container = document.getElementById('segmentsContainer');
            const segments = document.querySelectorAll('.segment-item');
            const segmentId = segments.length; // 使用当前分段数量作为新的segmentId

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
                    <label class="block text-sm font-medium text-gray-700 mb-2">预计时长（秒）</label>
                    <input type="number" name="segments[${segmentId}][duration]" id="duration-${segmentId}"
                           readonly
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed"
                           value="计算中..." disabled>
                    <p class="text-xs text-gray-500 mt-1">时长将根据文本内容自动计算</p>
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

            const newSegment = document.getElementById(`segment-${segmentId}`);
            const textarea = newSegment.querySelector('textarea');
            const durationInput = newSegment.querySelector(`#duration-${segmentId}`);

            textarea.addEventListener('input', function() {
                updateDurationInput(this, durationInput);
            });
        }

        function removeSegment(segmentId) {
            const segment = document.getElementById(`segment-${segmentId}`);
            if (segment) {
                segment.remove();
                renumberSegments();
            }
        }

        function updateSegmentNumbers() {
            renumberSegments();
        }

        addSegment();
    </script>
@endsection
