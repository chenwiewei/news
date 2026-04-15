<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VideoController extends Controller
{
    public function index()
    {
        $date = date('Ymd');
        //$user = exec('whoami');
        $baseDir = "/Users/cheniewiew/Desktop/video_create_$date";

        $projects = [];
        if (is_dir($baseDir)) {
            $folders = glob("$baseDir/*", GLOB_ONLYDIR);
            foreach ($folders as $folder) {
                $projectName = basename($folder);
                $videoFile = "$folder/{$projectName}.mp4";
                $projects[] = [
                    'name' => $projectName,
                    'path' => $folder,
                    'video_path' => file_exists($videoFile) ? $videoFile : null,
                    'created_at' => filectime($folder),
                ];
            }
            usort($projects, function($a, $b) {
                return $b['created_at'] - $a['created_at'];
            });
        }

        return view('video.index', compact('projects', 'baseDir'));
    }

    public function create()
    {
        return view('video.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'segments' => 'required|array|min:1',
            'segments.*.text' => 'required|string',
            'segments.*.image' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp',
            'segments.*.video' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska',
        ]);

        $date = date('Ymd_His');
        $baseDir = "/Users/cheniewiew/Desktop/video_create_{$date}/{$request->title}";

        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

        $debugUpload = [];
        $debugUpload[] = "收到 " . count($request->segments) . " 个分段";

        foreach ($request->segments as $index => $segmentData) {
            $debugUpload[] = "分段 {$index}: text=" . substr($segmentData['text'], 0, 50);

            if ($request->hasFile("segments.{$index}.image")) {
                $file = $request->file("segments.{$index}.image");
                $debugUpload[] = "  - 图片: {$file->getClientOriginalName()}, 大小: " . ($file->getSize() / 1024 / 1024) . "MB, MIME: {$file->getMimeType()}";
                $debugUpload[] = "  - 临时路径: {$file->getPathname()}";
                $debugUpload[] = "  - 是否有效: " . ($file->isValid() ? '是' : '否');
            } else {
                $debugUpload[] = "  - 图片: 未上传";
            }
        }

        file_put_contents("{$baseDir}/debug_store.txt", implode("\n", $debugUpload));

        $segments = [];
        $voice = 'Lilian';

        foreach ($request->segments as $index => $segmentData) {
            $text = $segmentData['text'];

            $tempAudioFile = tempnam(sys_get_temp_dir(), 'audio_calc_') . '.aiff';

            $command = sprintf(
                'say -v %s -o %s %s 2>&1',
                escapeshellarg($voice),
                escapeshellarg($tempAudioFile),
                escapeshellarg($text)
            );

            exec($command, $output, $returnVar);

            if ($returnVar === 0 && file_exists($tempAudioFile)) {
                $duration = $this->get_audio_duration($tempAudioFile);
                @unlink($tempAudioFile);
            } else {
                @unlink($tempAudioFile);
                $wordsCount = mb_strlen($text, 'UTF-8');
                $duration = max(2, ceil($wordsCount * 0.4));
            }

            $segment = [
                'text' => $text,
                'duration' => (int) ceil($duration),
                'order' => $index,
            ];

            if ($request->hasFile("segments.{$index}.image")) {
                $file = $request->file("segments.{$index}.image");

                try {
                    $imagePath = $file->storeAs(
                        'temp/' . uniqid(),
                        $file->getClientOriginalName(),
                        'public'
                    );
                    $fullPath = storage_path('app/public/' . $imagePath);

                    if (file_exists($fullPath)) {
                        $segment['image_path'] = $fullPath;
                    } else {
                        \Log::error("上传的文件不存在: {$fullPath}");
                    }
                } catch (\Exception $e) {
                    \Log::error("文件上传异常: " . $e->getMessage());
                }
            }

            if ($request->hasFile("segments.{$index}.video")) {
                $file = $request->file("segments.{$index}.video");

                try {
                    $videoPath = $file->storeAs(
                        'temp/' . uniqid(),
                        $file->getClientOriginalName(),
                        'public'
                    );
                    $fullPath = storage_path('app/public/' . $videoPath);

                    if (file_exists($fullPath)) {
                        $segment['video_path'] = $fullPath;
                    } else {
                        \Log::error("上传的视频文件不存在: {$fullPath}");
                    }
                } catch (\Exception $e) {
                    \Log::error("视频上传异常: " . $e->getMessage());
                }
            }

            $segments[] = $segment;
        }

        $projectData = [
            'title' => $request->title,
            'segments' => $segments,
            'base_dir' => $baseDir,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        file_put_contents("$baseDir/project.json", json_encode($projectData, JSON_UNESCAPED_UNICODE));

        return redirect()->route('video.generate', ['encodedPath' => $baseDir]);
    }

    public function generate($encodedPath)
    {
        $baseDir = $encodedPath;

        if (!is_dir($baseDir)) {
            abort(404, '项目不存在');
        }

        $projectData = json_decode(file_get_contents("$baseDir/project.json"), true);
        $title = $projectData['title'];
        $segments = $projectData['segments'];

        try {
            $successData = [];
            $imageIndex = 1;
            $debugInfo = [];

            foreach ($segments as $index => $segment) {
                $imageName = null;
                $hasVideo = false;

                if (!empty($segment['image_path'])) {
                    $sourcePath = $segment['image_path'];
                    $debugInfo[] = "分段 {$index}: image_path={$sourcePath}, exists=" . (file_exists($sourcePath) ? 'yes' : 'no');

                    if (file_exists($sourcePath)) {
                        $imageName = sprintf("%02d_image.png", $imageIndex);
                        $destPath = "$baseDir/$imageName";

                        $copyResult = copy($sourcePath, $destPath);

                        $debugInfo[] = "分段 {$index}: 复制到 {$destPath}, 结果=" . ($copyResult ? 'success' : 'failed') . ", 目标存在=" . (file_exists($destPath) ? 'yes' : 'no');

                        if ($copyResult && file_exists($destPath)) {
                            $imageIndex++;
                        } else {
                            $imageName = null;
                        }
                    }
                } elseif (!empty($segment['video_path'])) {
                    $sourcePath = $segment['video_path'];
                    $debugInfo[] = "分段 {$index}: video_path={$sourcePath}, exists=" . (file_exists($sourcePath) ? 'yes' : 'no');

                    if (file_exists($sourcePath)) {
                        $imageName = sprintf("%02d_video.mp4", $imageIndex);
                        $destPath = "$baseDir/$imageName";

                        if (copy($sourcePath, $destPath) && file_exists($destPath)) {
                            $hasVideo = true;
                            $imageIndex++;
                        } else {
                            $imageName = null;
                        }
                    }
                } else {
                    $debugInfo[] = "分段 {$index}: 没有 image_path 或 video_path";
                }

                $successData[] = [
                    'name' => "分段_" . ($index + 1),
                    'line' => $segment['text'],
                    'image' => $imageName,
                    'index' => $index + 1,
                    'duration' => $segment['duration'],
                    'has_video' => $hasVideo,
                ];
            }

            file_put_contents("{$baseDir}/debug_copy.txt", implode("\n", $debugInfo));

            $audioInfo = $this->generate_segmented_audio($baseDir, $successData);

            if ($audioInfo) {
                $this->generate_video($baseDir, $audioInfo, $successData, $title);
            }

        } catch (\Exception $e) {
            return view('video.generate', [
                'project' => (object)[
                    'title' => $title,
                    'base_dir' => $baseDir,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ],
            ]);
        }

        return view('video.generate', [
            'project' => (object)[
                'title' => $title,
                'base_dir' => $baseDir,
                'status' => 'completed',
            ],
        ]);
    }

    private function generate_segmented_audio($baseDir, $successData)
    {
        try {
            $voice = 'Lilian';
            $audioSegments = [];
            $totalDuration = 0;

            foreach ($successData as $item) {
                $text = $item['line'];
                $segIndex = $item['index'];

                $segmentFile = "{$baseDir}/audio_{$segIndex}.aiff";
                $command = sprintf(
                    'say -v %s -o %s %s',
                    escapeshellarg($voice),
                    escapeshellarg($segmentFile),
                    escapeshellarg($text)
                );

                exec($command, $output, $returnVar);

                if ($returnVar === 0 && file_exists($segmentFile)) {
                    $duration = $this->get_audio_duration($segmentFile);

                    $audioSegments[] = [
                        'path' => $segmentFile,
                        'duration' => $duration,
                        'index' => $segIndex,
                        'name' => $item['name'],
                        'line' => $item['line'],
                    ];

                    $totalDuration += $duration;
                } else {
                    throw new \Exception("第 {$segIndex} 段音频生成失败");
                }

                usleep(100000);
            }

            if (empty($audioSegments)) {
                throw new \Exception("所有音频片段生成失败");
            }

            $finalMp3 = "{$baseDir}/数据总结.mp3";
            $listFile = tempnam(sys_get_temp_dir(), 'ffmpeg_audio_');
            $listContent = "";

            foreach ($audioSegments as $seg) {
                $absolutePath = realpath($seg['path']);
                if ($absolutePath) {
                    $listContent .= "file '" . str_replace("'", "'\\''", $absolutePath) . "'\n";
                }
            }
            file_put_contents($listFile, $listContent);

            $mergeCommand = sprintf(
                'ffmpeg -y -f concat -safe 0 -i %s -c:a libmp3lame -b:a 128k %s 2>&1',
                escapeshellarg($listFile),
                escapeshellarg($finalMp3)
            );

            exec($mergeCommand, $mergeOutput, $mergeVar);
            @unlink($listFile);

            foreach ($audioSegments as $seg) {
                @unlink($seg['path']);
            }

            if ($mergeVar === 0 && file_exists($finalMp3)) {
                return [
                    'audio_path' => $finalMp3,
                    'duration' => $totalDuration,
                    'segments' => $audioSegments
                ];
            } else {
                throw new \Exception("音频合并失败");
            }

        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function generate_video($baseDir, $audioInfo, $successData, $title)
    {
        try {
            $audioPath = $audioInfo['audio_path'];
            $segments = $audioInfo['segments'];

            $date = date('Ymd');
            $outputVideo = "{$baseDir}/{$title}.mp4";
            $tempFile = tempnam(sys_get_temp_dir(), 'ffmpeg_');
            $listContent = "";
            $processedCount = 0;
            $debugLog = [];

            foreach ($segments as $seg) {
                $index = $seg['index'];
                $duration = $seg['duration'];
                $name = $seg['name'];
                $line = $seg['line'];

                $mediaPath = null;
                $isVideo = false;

                foreach ($successData as $item) {
                    if ($item['index'] === $index) {
                        if (!empty($item['image'])) {
                            $mediaPath = $baseDir . '/' . $item['image'];
                            $isVideo = $item['has_video'];
                        }
                        break;
                    }
                }

                $debugLog[] = "分段 {$index}: mediaPath=" . ($mediaPath ?? 'null') . ", exists=" . ($mediaPath ? (file_exists($mediaPath) ? 'yes' : 'no') : 'N/A');

                if (!$mediaPath || !file_exists($mediaPath)) {
                    continue;
                }

                $clipFile = "{$baseDir}/clip_{$index}.mp4";

                if ($isVideo && pathinfo($mediaPath, PATHINFO_EXTENSION) === 'mp4') {
                    copy($mediaPath, $clipFile);
                    $debugLog[] = "分段 {$index}: 视频文件已复制";
                } else {
                    $finalImage = $mediaPath;

                    if (!empty($line)) {
                        $imageWithText = "{$baseDir}/image_{$index}_with_text.png";
                        $textImage = "{$baseDir}/text_{$index}.png";

                        $createTextCmd = sprintf(
                            'magick xc:none -size 1920x80 -font "%s" -pointsize 40 -fill black -stroke black -strokewidth 1 -gravity center caption:"%s" png32:%s 2>&1',
                            "/System/Library/Fonts/STHeiti Medium.ttc",
                            addslashes($line),
                            escapeshellarg($textImage)
                        );

                        exec($createTextCmd, $textOutput, $textReturn);

                        $debugLog[] = "分段 {$index}: 文字图层创建返回码={$textReturn}, 文件存在=" . (file_exists($textImage) ? 'yes' : 'no');

                        if ($textReturn === 0 && file_exists($textImage)) {
                            $compositeCmd = sprintf(
                                'magick %s -resize 1920x1040 -gravity center -background black -extent 1920x1080 %s -gravity South -geometry +0+0 -compose over -composite %s 2>&1',
                                escapeshellarg($mediaPath),
                                escapeshellarg("{$baseDir}/text_{$index}-1.png"),
                                escapeshellarg($imageWithText)
                            );

                            exec($compositeCmd, $compOutput, $compReturn);

                            $debugLog[] = "分段 {$index}: 图片合成返回码={$compReturn}, 文件存在=" . (file_exists($imageWithText) ? 'yes' : 'no');

                            @unlink($textImage);
                            @unlink("{$baseDir}/text_{$index}-0.png");
                            @unlink("{$baseDir}/text_{$index}-1.png");

                            if ($compReturn === 0 && file_exists($imageWithText)) {
                                $finalImage = $imageWithText;
                            }
                        }
                    }

                    $command = sprintf(
                        'ffmpeg -y -loop 1 -i %s -c:v h264 -t %f -vf "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2" -pix_fmt yuv420p -r 30 %s 2>&1',
                        escapeshellarg($finalImage),
                        $duration,
                        escapeshellarg($clipFile)
                    );

                    exec($command, $output, $returnVar);

                    $debugLog[] = "分段 {$index}: FFmpeg 返回码={$returnVar}, clip 存在=" . (file_exists($clipFile) ? 'yes' : 'no');

                    @unlink($imageWithText);
                }

                if (file_exists($clipFile)) {
                    $listContent .= "file '" . str_replace("'", "'\\''", realpath($clipFile)) . "'\n";
                    $processedCount++;
                }
            }

            file_put_contents("{$baseDir}/debug_log.txt", implode("\n", $debugLog));

            if ($processedCount === 0) {
                throw new \Exception("没有有效的视频片段可以合并（共检查了 " . count($segments) . " 个分段）\n调试信息:\n" . implode("\n", $debugLog));
            }

            file_put_contents($tempFile, $listContent);

            $mergeCommand = sprintf(
                'ffmpeg -y -f concat -safe 0 -i %s -i %s -c:v copy -c:a aac -b:a 128k -shortest %s 2>&1',
                escapeshellarg($tempFile),
                escapeshellarg($audioPath),
                escapeshellarg($outputVideo)
            );

            exec($mergeCommand, $mergeOutput, $mergeVar);

            if ($mergeVar !== 0) {
                file_put_contents("{$baseDir}/ffmpeg_merge_error.log", implode("\n", $mergeOutput));
            }

            @unlink($tempFile);

            foreach ($segments as $seg) {
                @unlink("{$baseDir}/clip_{$seg['index']}.mp4");
            }

            if ($mergeVar !== 0 || !file_exists($outputVideo)) {
                throw new \Exception("视频合并失败，返回码: {$mergeVar}");
            }

        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function get_audio_duration($audioPath)
    {
        try {
            $command = sprintf(
                'ffprobe -i %s -show_entries format=duration -v quiet -of csv="p=0" 2>&1',
                escapeshellarg($audioPath)
            );

            exec($command, $output, $returnVar);

            if ($returnVar === 0 && !empty($output)) {
                return floatval(trim($output[0]));
            }

            return 60.0;

        } catch (\Exception $e) {
            return 60.0;
        }
    }

    public function download($encodedPath)
    {
        $baseDir = $encodedPath;

        if (!is_dir($baseDir)) {
            abort(404, '项目不存在');
        }

        $projectData = json_decode(file_get_contents("$baseDir/project.json"), true);
        $title = $projectData['title'];
        $videoFile = "$baseDir/{$title}.mp4";

        if (!file_exists($videoFile)) {
            abort(404, '视频文件不存在');
        }

        return response()->download($videoFile);
    }
}
