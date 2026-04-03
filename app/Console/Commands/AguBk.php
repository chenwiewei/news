<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AguBk extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agu_bk';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '收集 A 股各个版块涨跌幅和资金流入情况（中午 11:40 和下午 3:10）';


    private $sectorList = [
        // 行业板块
        '半导体' => 'BK1036',
        /*'银行' => 'BK0475',
        '证券' => 'BK0473',
        '保险' => 'BK0476',
        '房地产' => 'BK0477',
        '石油' => 'BK0488',
        '煤炭' => 'BK0489',
        '钢铁' => 'BK0491',
        '有色金属' => 'BK0492',
        '化工' => 'BK0495',
        '建材' => 'BK0497',
        '建筑' => 'BK0478',
        '机械' => 'BK0480',
        '汽车' => 'BK0481',
        '家电' => 'BK0482',
        '纺织' => 'BK0483',
        '农业' => 'BK0484',
        '食品' => 'BK0485',
        '医药' => 'BK0486',
        '生物' => 'BK0487',
        '电子' => 'BK0493',
        '通信' => 'BK0496',
        '计算机' => 'BK0498',
        '传媒' => 'BK0499',
        '国防' => 'BK0500',
        '电力' => 'BK0501',
        '交运' => 'BK0502',
        '商贸' => 'BK0503',
        '旅游' => 'BK0504',
        '环保' => 'BK0505',

        // 概念板块（热门）
        '人工智能' => 'BK0800',
        '新能源' => 'BK1002',
        '芯片' => 'BK1003',
        '5G' => 'BK1004',
        '物联网' => 'BK1005',
        '云计算' => 'BK1006',
        '大数据' => 'BK1007',
        '区块链' => 'BK1008',
        '智能汽车' => 'BK1009',*/
        '机器人' => 'BK1184',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        date_default_timezone_set('Asia/Shanghai');

        // ===================== 配置区域 =====================
        $user = 'cheniewiew';
        // ===================================================

        $currentTime = date('H:i');
        $date = date('Ymd');
        $timeFlag = $currentTime < '15:00' ? '午间' : '收盘';

        $baseDir = "/Users/$user/Desktop/A 股板块数据_{$date}_{$timeFlag}";
        if (!is_dir($baseDir)) mkdir($baseDir, 0777, true);

        $content = "A 股板块监控 - {$timeFlag}复盘\n";
        $content .= "更新时间：" . date('Y-m-d H:i') . "\n";
        $content .= "数据来源：东方财富网\n\n";

        $successData = [];
        $imageIndex = 1;

        $this->info("开始获取 A 股板块数据（{$timeFlag}）...");

        foreach ($this->sectorList as $name => $blockCode) {
            try {
                $this->info("正在获取：{$name} (板块代码：{$blockCode})");

                $data = $this->getSectorData($blockCode, $name);

                if ($data === null) {
                    $line = "{$name}：数据获取失败\n";
                    $content .= $line;
                    $this->error($line);
                    continue;
                }

                $changePercent = $data['changePercent'];
                $netInflow = $data['netInflow'];
                $changeRaw = $data['changeRaw'];

                // 判断上涨/下跌
                $trend = $changeRaw > 0 ? '上涨' : ($changeRaw < 0 ? '下跌' : '持平');

                // 格式化资金流入
                $inflowText = $this->formatInflow($netInflow);

                $line = "{$name}：{$trend}{$changePercent}，资金{$inflowText}\n";
                $content .= $line;
                $this->info($line);

                // 记录成功数据
                $successData[] = [
                    'name' => $name,
                    'line' => trim($line),
                    'index' => $imageIndex,
                    'blockCode' => $blockCode,
                    'changePercent' => $changePercent,
                    'netInflow' => $netInflow,
                ];
                $imageIndex++;

            } catch (\Exception $e) {
                $line = "{$name}：获取失败 - " . $e->getMessage() . "\n";
                $content .= $line;
                $this->error($line);
            }

            usleep(300000); // 0.3 秒延迟
        }

        // 按涨跌幅排序
        usort($successData, function($a, $b) {
            return floatval(str_replace('%', '', $b['changePercent'])) -
                floatval(str_replace('%', '', $a['changePercent']));
        });

        $content .= "\n=== 涨幅榜 ===\n";
        foreach (array_slice($successData, 0, 10) as $item) {
            $content .= $item['line'] . "\n";
        }

        $content .= "\n=== 跌幅榜 ===\n";
        foreach (array_slice($successData, -10) as $item) {
            $content .= $item['line'] . "\n";
        }

        file_put_contents("{$baseDir}/数据总结.txt", $content);

        // 生成音频和视频
        $audioInfo = $this->generateSegmentedAudio($baseDir, $successData);
        if ($audioInfo) {
            $this->generateVideo($baseDir, $audioInfo, $successData);
        }

        $this->info("\n全部完成！数据已保存至：{$baseDir}");

        return 0;
    }

    private function getSectorData($blockCode, $name) {
        sleep(1);
        try {
            // 东方财富板块行情 API
            $url = "https://push2.eastmoney.com/api/qt/stock/get";

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.3.1 Safari/605.1.15',
                //'Referer' => 'https://quote.eastmoney.com/',
                'Accept' => 'application/json,text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Connection' => 'keep-alive',
                'Accept-Encoding' => 'gzip, deflate, br, zstd',
                'Accept-Language' => 'zh-CN,zh-Hans;q=0.9',
                'Cookie' => 'st_inirUrl=https%3A%2F%2Fbaidu.com%2F; st_psi=2026040314103033-111000300841-4486875241; st_pvi=43185757987776; st_sn=705; st_sp=2026-03-17%2021%3A04%3A59; st_asi=delete; emshistory=%5B%22%E6%81%92%E7%94%9F%E5%8C%BB%E8%8D%AF%22%2C%22%E6%81%92%E7%94%9F%E7%A7%91%E6%8A%80%22%2C%22%E5%8D%81%E5%B9%B4%E6%9C%9F%E7%BE%8E%E5%80%BA%22%2C%22%E9%87%91%E8%9E%8DETF-SPDR%22%2C%22xlf%22%2C%22xlF%22%2C%22xlv%22%2C%22%E9%9F%A9%E5%9B%BD%22%2C%22%E7%BE%8E%E5%8E%9F%E6%B2%B9%22%2C%22%E5%B8%83%E4%BC%A6%E7%89%B9%E5%8E%9F%E6%B2%B9%22%5D; fullscreengg=1; fullscreengg2=1; gviem=4NTJOJAcXh9omajR8Unpu5397; gviem_create_time=1773752700502; nid18=07ae44c788fc3c3386bd4c0eca23bd0b; nid18_create_time=1773752700502; qgqp_b_id=eb47925c3f0aaaf096879f27128adae9; st_nvi=unqJMqHLBAppK28Ze95e2373d; st_si=77226005996683',
                'Priority' => 'u=0, i',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none'
            ])
                ->withOptions([
                    'version' => 1.1,
                    'curl' => [CURLOPT_TIMEOUT => 15]
                ])
                ->timeout(15)
                ->get($url, [
                    'secid' => "90.$blockCode",  // 东财板块代码前缀 90.
                    'fields' => 'f43,f169,f170,f184,f185',
                    'fid' => 'f62',
                    'po' => '1',
                    'pz' => '100',
                    'pn' => '1',
                    'np' => '1',
                    'fltt' => '2',
                    'invt' => '2',
                    'ut' => 'b2884a393a59ad64002292a3e90d46a5',
                    'fs' => 'm:90 t:3'
                    // f43=现价，f169=涨跌额，f170=涨跌幅，f267=主力净流入 (元)，f268=主力净流入占比 (%)
                ]);

            if (!$response->successful()) {
                $this->error("{$name} HTTP 状态码：{$response->status()}");
                return null;
            }

            $result = $response->json();
            dd($result);
            if (!isset($result['data']) || empty($result['data'])) {
                $this->error("{$name} 返回数据为空");
                return null;
            }

            $data = $result['data'];

            $percentRaw = floatval($data['f170'] ?? 0);
            $changeRaw = floatval($data['f169'] ?? 0);
            $netInflowRaw = floatval($data['f267'] ?? 0); // 主力净流入（元）

            $percent = $percentRaw / 100;
            $netInflow = $netInflowRaw / 100000000; // 转换为亿

            return [
                'price' => number_format(floatval($data['f43'] ?? 0) / 100, 2),
                'changePercent' => abs(number_format($percent, 2)) . "%",
                'changeRaw' => $changeRaw / 100,
                'netInflow' => $netInflow,
            ];

        } catch (\Exception $e) {
            $this->error("{$name} 异常：" . $e->getMessage());
            return null;
        }
    }

    private function formatInflow($inflow) {
        if ($inflow > 0) {
            return "流入" . number_format(abs($inflow), 2) . "亿";
        } elseif ($inflow < 0) {
            return "流出" . number_format(abs($inflow), 2) . "亿";
        } else {
            return "平衡";
        }
    }

    private function generateSegmentedAudio($baseDir, $successData) {
        try {
            $this->info("正在分段生成音频文件...");

            $voice = 'Lilian';
            $audioSegments = [];
            $totalDuration = 0;

            foreach ($successData as $index => $item) {
                $text = $item['line'];
                $segIndex = $item['index'];

                $this->info("生成第 {$segIndex} 段音频：{$item['name']}");

                $segmentFile = "{$baseDir}/audio_{$segIndex}.aiff";
                $command = sprintf(
                    'say -v %s -o %s %s',
                    escapeshellarg($voice),
                    escapeshellarg($segmentFile),
                    escapeshellarg($text)
                );

                exec($command, $output, $returnVar);

                if ($returnVar === 0 && file_exists($segmentFile)) {
                    $duration = $this->getAudioDuration($segmentFile);

                    $audioSegments[] = [
                        'path' => $segmentFile,
                        'duration' => $duration,
                        'index' => $segIndex,
                        'name' => $item['name'],
                        'line' => $item['line'],
                    ];

                    $totalDuration += $duration;
                    $this->info("第 {$segIndex} 段音频生成成功，时长：" . round($duration, 2) . " 秒");
                } else {
                    $this->error("第 {$segIndex} 段音频生成失败");
                }

                usleep(100000);
            }

            if (empty($audioSegments)) {
                $this->error("所有音频片段生成失败");
                return null;
            }

            $this->info("正在合并 " . count($audioSegments) . " 个音频片段...");

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
                $fileSize = filesize($finalMp3);
                $this->info("音频合并成功：{$finalMp3} (大小：" . number_format($fileSize / 1024, 2) . " KB)");

                return [
                    'audio_path' => $finalMp3,
                    'duration' => $totalDuration,
                    'segments' => $audioSegments
                ];
            } else {
                $this->error("音频合并失败");
                return $this->mergeAudioWithCat($audioSegments, $baseDir, $totalDuration);
            }

        } catch (\Exception $e) {
            $this->error("分段生成音频时出错：" . $e->getMessage());
            return null;
        }
    }

    private function mergeAudioWithCat($audioSegments, $baseDir, $totalDuration) {
        try {
            $finalAiff = "{$baseDir}/数据总结.aiff";
            $finalMp3 = "{$baseDir}/数据总结.mp3";

            $inputFiles = implode(' ', array_map(function($seg) {
                return escapeshellarg($seg['path']);
            }, $audioSegments));

            $soxCommand = "sox {$inputFiles} {$finalAiff} 2>&1";
            exec($soxCommand, $soxOutput, $soxVar);

            if ($soxVar === 0 && file_exists($finalAiff)) {
                $convertCommand = sprintf(
                    'afconvert -f mp4f -d aac -b 128000 %s %s 2>&1',
                    escapeshellarg($finalAiff),
                    escapeshellarg($finalMp3)
                );
                exec($convertCommand, $convertOutput, $convertVar);

                if ($convertVar === 0 && file_exists($finalMp3)) {
                    @unlink($finalAiff);
                    foreach ($audioSegments as $seg) {
                        @unlink($seg['path']);
                    }

                    $fileSize = filesize($finalMp3);
                    $this->info("使用 SoX 合并成功：{$finalMp3} (大小：" . number_format($fileSize / 1024, 2) . " KB)");

                    return [
                        'audio_path' => $finalMp3,
                        'duration' => $totalDuration,
                        'segments' => $audioSegments
                    ];
                }
            }

            return null;

        } catch (\Exception $e) {
            $this->error("备用合并方案失败：" . $e->getMessage());
            return null;
        }
    }

    private function getAudioDuration($audioPath) {
        try {
            $command = sprintf(
                'ffprobe -i %s -show_entries format=duration -v quiet -of csv="p=0" 2>&1',
                escapeshellarg($audioPath)
            );

            exec($command, $output, $returnVar);

            if ($returnVar === 0 && !empty($output)) {
                return floatval(trim($output[0]));
            }

            $afCommand = sprintf(
                'afinfo %s 2>&1 | grep "duration:" | awk \'{print $2}\'',
                escapeshellarg($audioPath)
            );

            exec($afCommand, $afOutput, $afVar);

            if ($afVar === 0 && !empty($afOutput)) {
                return floatval(trim($afOutput[0]));
            }

            return 60.0;

        } catch (\Exception $e) {
            return 60.0;
        }
    }

    private function generateVideo($baseDir, $audioInfo, $successData) {
        try {
            $audioPath = $audioInfo['audio_path'];
            $segments = $audioInfo['segments'] ?? [];

            $this->info("正在生成视频文件...");

            if (!empty($segments) && !empty($successData)) {
                $this->info("使用分段音频时长生成视频，共 " . count($segments) . " 段");

                $date = date('Ymd');
                $timeFlag = date('H') < 15 ? '午间' : '收盘';
                $outputVideo = "{$baseDir}/{$date}A 股板块监控_{$timeFlag}.mp4";
                $tempFile = tempnam(sys_get_temp_dir(), 'ffmpeg_');
                $listContent = "";

                foreach ($segments as $seg) {
                    $index = $seg['index'];
                    $duration = $seg['duration'];
                    $name = $seg['name'];
                    $line = $seg['line'];

                    // 创建带文字的图片
                    $imageWithText = "{$baseDir}/image_{$index}_with_text.png";
                    $textImage = "{$baseDir}/text_{$index}.png";

                    $createTextCmd = sprintf(
                        'magick xc:none -size 1920x80 -font "%s" -pointsize 40 -fill black -stroke black -strokewidth 1 -gravity center caption:"%s" png32:%s 2>&1',
                        "/System/Library/Fonts/STHeiti Medium.ttc",
                        addslashes($line),
                        escapeshellarg($textImage)
                    );

                    $this->info("创建文字图层...");
                    exec($createTextCmd, $textOutput, $textReturn);

                    if ($textReturn === 0) {
                        $compositeCmd = sprintf(
                            'magick xc:black -size 1920x1080 gradient:black-gray -gravity center -font "%s" -pointsize 50 -fill white -annotate +0+0 "%s" -annotate +0+50 "涨跌幅 + 资金流向" png32:%s 2>&1',
                            "/System/Library/Fonts/STHeiti Medium.ttc",
                            addslashes($name),
                            escapeshellarg($imageWithText)
                        );

                        exec($compositeCmd, $compOutput, $compReturn);
                        @unlink($textImage);

                        if ($compReturn !== 0 || !file_exists($imageWithText)) {
                            $this->warn("图片合成失败");
                            continue;
                        }
                    } else {
                        $this->warn("文字图层创建失败");
                        continue;
                    }

                    $clipFile = "{$baseDir}/clip_{$index}.mp4";
                    $command = sprintf(
                        'ffmpeg -y -loop 1 -i %s -c:v h264 -t %f -vf "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2" -pix_fmt yuv420p -r 30 %s 2>&1',
                        escapeshellarg($imageWithText),
                        $duration,
                        escapeshellarg($clipFile)
                    );

                    $this->info("处理图片 #{$index}: {$name} (时长：" . round($duration, 2) . "秒)");
                    exec($command, $output, $returnVar);

                    if ($returnVar === 0 && file_exists($clipFile)) {
                        $listContent .= "file '" . str_replace("'", "'\\''", $clipFile) . "'\n";
                        $this->info("✓ 视频片段创建成功：{$name}");
                        @unlink($imageWithText);
                    } else {
                        $this->warn("创建视频片段失败：{$name}");
                        @unlink($imageWithText);
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

                if ($mergeVar === 0 && file_exists($outputVideo)) {
                    $fileSize = filesize($outputVideo);
                    $videoDuration = $this->getVideoDuration($outputVideo);
                    $this->info("视频文件生成成功：{$outputVideo}");
                    $this->info("视频时长：" . round($videoDuration, 2) . " 秒，大小：" . number_format($fileSize / 1024 / 1024, 2) . " MB");
                    return true;
                } else {
                    $this->error("视频合并失败");
                    return false;
                }
            }

            $this->error("无法生成视频：缺少必要数据");
            return false;

        } catch (\Exception $e) {
            $this->error("生成视频时出错：" . $e->getMessage());
            return false;
        }
    }

    private function getVideoDuration($videoPath) {
        try {
            $command = sprintf(
                'ffprobe -i %s -show_entries format=duration -v quiet -of csv="p=0" 2>&1',
                escapeshellarg($videoPath)
            );

            exec($command, $output, $returnVar);

            if ($returnVar === 0 && !empty($output)) {
                return floatval(trim($output[0]));
            }

            return 0;

        } catch (\Exception $e) {
            return 0;
        }
    }
}
