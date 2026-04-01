<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VideoController extends Controller
{
    public function index()
    {
        $date = date('Ymd');
        $user = exec('whoami');
        $baseDir = "/Users/$user/Desktop/视频生成项目_$date";

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
            'segments.*.duration' => 'required|integer|min:1',
            'segments.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'segments.*.video' => 'nullable|mimetypes:video/mp4,video/quicktime,video/x-msvideo|max:102400',
        ]);

        $user = exec('whoami');
        $date = date('Ymd_His');
        $baseDir = "/Users/$user/Desktop/视频生成项目_{$date}/{$request->title}";

        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

        $segments = [];

        foreach ($request->segments as $index => $segmentData) {
            $segment = [
                'text' => $segmentData['text'],
                'duration' => (int) $segmentData['duration'],
                'order' => $index,
            ];

            if ($request->hasFile("segments.{$index}.image")) {
                $imagePath = $request->file("segments.{$index}.image")->storeAs(
                    'temp/' . uniqid(),
                    $request->file("segments.{$index}.image")->getClientOriginalName()
                );
                $segment['image_path'] = storage_path('app/' . $imagePath);
            }

            if ($request->hasFile("segments.{$index}.video")) {
                $videoPath = $request->file("segments.{$index}.video")->storeAs(
                    'temp/' . uniqid(),
                    $request->file("segments.{$index}.video")->getClientOriginalName()
                );
                $segment['video_path'] = storage_path('app/' . $videoPath);
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

        return redirect()->route('video.generate', base64_encode($baseDir));
    }

    public function generate($encodedPath)
    {
        $baseDir = base64_decode($encodedPath);

        if (!is_dir($baseDir)) {
            abort(404, '项目不存在');
        }

        $projectData = json_decode(file_get_contents("$baseDir/project.json"), true);
        $title = $projectData['title'];
        $segments = $projectData['segments'];

        try {
            $successData = [];
            $imageIndex = 1;

            foreach ($segments as $index => $segment) {
                $imageName = null;

                if (!empty($segment['image_path']) && file_exists($segment['image_path'])) {
                    $imageName = sprintf("%02d_%s.png", $imageIndex, $segment['text']);
                    $destPath = "$baseDir/$imageName";

                    if (pathinfo($segment['image_path'], PATHINFO_EXTENSION) !== 'png') {
                        $convertCmd = sprintf(
                            'magick %s %s 2>&1',
                            escapeshellarg($segment['image_path']),
                            escapeshellarg($destPath)
                        );
                        exec($convertCmd, $output, $returnVar);
                        if ($returnVar !== 0) {
                            copy($segment['image_path'], $destPath);
                        }
                    } else {
                        copy($segment['image_path'], $destPath);
                    }

                    $imageIndex++;
                } elseif (!empty($segment['video_path']) && file_exists($segment['video_path'])) {
                    $videoName = sprintf("%02d_video_%s.mp4", $imageIndex, $segment['text']);
                    $destPath = "$baseDir/$videoName";
                    copy($segment['video_path'], $destPath);
                    $imageName = $videoName;
                    $imageIndex++;
                }

                $successData[] = [
                    'name' => "分段_" . ($index + 1),
                    'line' => $segment['text'],
                    'image' => $imageName,
                    'index' => $index + 1,
                    'duration' => $segment['duration'],
                    'has_video' => !empty($segment['video_path']),
                ];
            }

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

            foreach ($segments as $seg) {
                $index = $seg['index'];
                $duration = $seg['duration'];
                $name = $seg['name'];
                $line = $seg['line'];

                $imagePath = null;
                foreach ($successData as $item) {
                    if ($item['index'] === $index) {
                        if ($item['has_video']) {
                            $imagePath = $baseDir . '/' . $item['image'];
                        } else {
                            $imagePath = $baseDir . '/' . $item['image'];
                        }
                        break;
                    }
                }

                if (!$imagePath || !file_exists($imagePath)) {
                    continue;
                }

                $clipFile = "{$baseDir}/clip_{$index}.mp4";

                if (pathinfo($imagePath, PATHINFO_EXTENSION) === 'mp4') {
                    $probeCmd = sprintf(
                        'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s',
                        escapeshellarg($imagePath)
                    );
                    exec($probeCmd, $videoDurationOutput, $videoReturnVar);
                    $videoDuration = floatval(trim($videoDurationOutput[0] ?? 0));

                    if ($videoDuration > $duration) {
                        $trimCmd = sprintf(
                            'ffmpeg -y -i %s -t %f -c:v copy -c:a copy %s 2>&1',
                            escapeshellarg($imagePath),
                            $duration,
                            escapeshellarg($clipFile)
                        );
                        exec($trimCmd, $trimOutput, $trimReturnVar);
                    } else {
                        $clipFile = $imagePath;
                    }
                } else {
                    $imageWithText = "{$baseDir}/image_{$index}_with_text.png";
                    $textImage = "{$baseDir}/text_{$index}.png";

                    $createTextCmd = sprintf(
                        'magick xc:none -size 1920x80 -font "%s" -pointsize 40 -fill black -stroke black -strokewidth 1 -gravity center caption:"%s" png32:%s 2>&1',
                        "/System/Library/Fonts/STHeiti Medium.ttc",
                        addslashes($line),
                        escapeshellarg($textImage)
                    );

                    exec($createTextCmd, $textOutput, $textReturn);

                    if ($textReturn === 0 && file_exists($textImage)) {
                        $compositeCmd = sprintf(
                            'magick %s -resize 1920x1040 -gravity center -background black -extent 1920x1080 %s -gravity South -geometry +0+0 -compose over -composite %s 2>&1',
                            escapeshellarg($imagePath),
                            escapeshellarg($textImage),
                            escapeshellarg($imageWithText)
                        );

                        exec($compositeCmd, $compOutput, $compReturn);
                        @unlink($textImage);

                        if ($compReturn === 0 && file_exists($imageWithText)) {
                            $finalImage = $imageWithText;
                        } else {
                            $finalImage = $imagePath;
                        }
                    } else {
                        $finalImage = $imagePath;
                        @unlink($textImage);
                    }

                    $command = sprintf(
                        'ffmpeg -y -loop 1 -i %s -c:v h264 -t %f -vf "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2" -pix_fmt yuv420p -r 30 %s 2>&1',
                        escapeshellarg($finalImage),
                        $duration,
                        escapeshellarg($clipFile)
                    );

                    exec($command, $output, $returnVar);
                    @unlink($imageWithText);
                }

                if (file_exists($clipFile)) {
                    $listContent .= "file '" . str_replace("'", "'\\''", realpath($clipFile)) . "'\n";
                }
            }

            file_put_contents($tempFile, $listContent);

            $mergeCommand = sprintf(
                'ffmpeg -y -f concat -safe 0 -i %s -i %s -c:v copy -c:a aac -b:a 128k -shortest %s 2>&1',
                escapeshellarg($tempFile),
                escapeshellarg($audioPath),
                escapeshellarg($outputVideo)
            );

            exec($mergeCommand, $mergeOutput, $mergeVar);
            @unlink($tempFile);

            foreach ($segments as $seg) {
                @unlink("{$baseDir}/clip_{$seg['index']}.mp4");
            }

            if ($mergeVar !== 0 || !file_exists($outputVideo)) {
                throw new \Exception("视频合并失败");
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
        $baseDir = base64_decode($encodedPath);

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
