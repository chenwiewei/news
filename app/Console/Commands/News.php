<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class News extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取隔夜美股、欧洲等市场的指数和重要板块指数涨跌幅度以及走势图';


    private $indexList = [
        '富时中国 A 50' => '100.XIN9',  // 富时中国 A50 指数 (新加坡)

        // 大宗商品与汇率
        '布伦特原油' => '112.B00Y',    // ICE 布伦特原油
        '现货黄金' => '122.XAU',      // COMEX 黄金

        // 美洲市场（美股）
        '道琼斯' => '100.DJIA',      // 道琼斯工业平均指数
        '纳斯达克' => '100.NDX',  // 纳斯达克综合指数
        '标普 500' => '100.SPX',     // 标普 500 指数
        '美元' => '100.UDI',     // 美元指数
        //'美原油' => '102.CL00Y',        // NYMEX 原油
        '美股重要板块：科技行业' => '107.XLK',     // 美股 科技行业精选
        '通信服务' => '107.XLC',     // 美股 科技行业精选
        '医疗板块' => '107.XLV',     // 美股 科技行业精选
        '金融板块' => '107.XLF',     // 美股 科技行业精选
        '必需消费' => '107.XLP',     // 美股 科技行业精选
        '可选消费' => '107.XLY',     // 美股 科技行业精选
        '公用事业' => '107.XLU',     // 美股 科技行业精选
        '基础材料' => '107.XLB',     // 美股 科技行业精选

        // 亚太市场
        //'恒生指数' => '100.HSI',     // 恒生指数
        '日经' => '100.N225',    // 日经 225 指数
        '韩国' => '100.KS11',    // 日经 225 指数

        // 欧洲市场
        '英国' => '100.FTSE', // 英国富时 100
        '德国' => '100.GDAXI',   // 德国 DAX30
        '法国' => '100.FCHI',  // 法国 CAC40
    ];

    private $noIndexSecid = [
        '107.XLK' => '英伟达 苹果 微软 博通 AMD',
        '107.XLC' => '谷歌 Meta 奈飞 威瑞森 T-Mobile ',
        '107.XLV' => '联合健康 强生 礼来 辉瑞 默克',
        '107.XLF' => '伯克希尔哈撒韦 摩根大通 美国银行 高盛 花旗',
        '107.XLP' => '宝洁 可口可乐 沃尔玛 百事可乐 好时',
        '107.XLY' => '亚马逊 特斯拉 家得宝 麦当劳 耐克',
        '107.XLU' => '杜克能源 南方公司 美国电力 新纪元能源',
        '107.XLB' => '纽柯钢铁 美铝 PPG 工业 利尔化学',
        '100.XIN9' => '',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        date_default_timezone_set('Asia/Shanghai');

// ===================== 只改这里：你的 Mac 用户名 =====================
        $user = 'cheniewiew';
// ==================================================================

        $date = date('Ymd');
        $baseDir = "/Users/$user/Desktop/隔夜全球市场数据_$date";
        if (!is_dir($baseDir)) mkdir($baseDir, 0777, true);

// 全球指数代码（按市场分类）- 使用东方财富正确的证券 ID 格式
        $indexList = $this->indexList;

        $noIndexSecid = $this->noIndexSecid;

        $content = "隔夜全球市场复盘，A 股开盘参考。\n";
        $content .= "更新时间：" . date('Y-m-d') . "。\n";
        //$content .= str_repeat('-', 60) . "\n";

        // 存储成功获取的数据，用于后续视频生成
        $successData = [];
        $imageIndex = 1;

// -------------------------- 主逻辑 --------------------------
        foreach ($indexList as $name => $secid) {
            try {
                $this->info("正在获取：{$name} (证券 ID: {$secid})");

                $data = $this->get_data_from_eastmoney($secid, $name);

                if ($data === null) {
                    $line = "{$name}：数据获取失败\n";
                    $content .= $line;
                    $this->error($line);
                    continue;
                }

                $price = str_replace('.', '点', (string)$data['price']);
                $change = $data['change'];
                $percent = $data['percent'];
                $changeRaw = $data['change_raw'];

                // 判断上涨/下跌
                $trend = $changeRaw > 0 ? '上涨' : ($changeRaw < 0 ? '下跌' : '持平');
                $changeAbs = abs($changeRaw);

                // 格式化输出：名称：指数收 xx 点，上涨/下跌 xx 点、xx%
                if(array_key_exists($secid, $noIndexSecid)){
                    $line = "{$name}：{$trend}" . $percent . "\n";
                }else{
                    $line = "{$name}：指数收{$price}，{$trend}"  . $percent . "\n";
                }
                $content .= $line;
                $this->info($line);

                // 下载走势图（传入序号）
                $imageName = $this->download_chart($name, $secid, $baseDir, $imageIndex);

                if ($imageName) {
                    // 记录成功的数据和图片映射关系
                    $successData[] = [
                        'name' => $name,
                        'line' => trim($line),
                        'image' => $imageName,
                        'index' => $imageIndex,
                        'secId' => $secid,
                    ];
                    $imageIndex++;
                }

            } catch (\Exception $e) {
                $line = "{$name}：获取失败 - " . $e->getMessage() . "\n";
                $content .= $line;
                $this->error($line);
            }

            // 添加短暂延迟，避免请求过快
            usleep(500000); // 0.5 秒
        }

        //$content .= str_repeat('-', 60) . "\n";
        //$content .= "数据来源：东方财富网\n";
        $content .= "有需要其他数据的可在评论区留言，明早加上。\n";

        file_put_contents("{$baseDir}/数据总结.txt", $content);

        // 分段生成音频并计算时长
        $audioInfo = $this->generate_segmented_audio($baseDir, $successData);
        if ($audioInfo) {
            // 使用分段音频信息生成视频
            $this->generate_video($baseDir, $audioInfo, $successData);
        }

        $this->info("\n 全部完成！数据已保存至：{$baseDir}");

        return 0;
    }

// -------------------------- 函数 1：东方财富 API --------------------------
    private function get_data_from_eastmoney($secid, $name) {
        // 东方财富全球指数实时行情 API
        $url = "https://push2.eastmoney.com/api/qt/stock/get";

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.3.1 Safari/605.1.15',
                'Referer' => 'https://quote.eastmoney.com/',
                'Accept' => 'application/json, text/javascript, */*; q=0.01'
            ])
                ->withOptions([
                    'version' => 1.1, // 强制HTTP/1.1，解决服务器断开问题
                    'curl' => [
                        CURLOPT_TIMEOUT => 15,
                    ]
                ])
                ->timeout(15)
                ->get($url, [
                    'secid' => $secid,
                    'fields' => 'f43,f169,f170'  // f43=当前价，f169=涨跌额，f170=涨跌幅
                ]);

            if (!$response->successful()) {
                $this->error("{$name} HTTP 状态码：{$response->status()}");
                return null;
            }

            $result = $response->json();
            //lt);
            if (!isset($result['data']) || empty($result['data'])) {
                $this->error("{$name} 返回数据为空 - rc: " . ($result['rc'] ?? 'unknown'));
                return null;
            }

            $data = $result['data'];

            // 获取关键数据并打印原始值用于调试
            $priceRaw = floatval($data['f43'] ?? 0); // 当前价
            $changeRaw = floatval($data['f169'] ?? 0); // 涨跌额 (需要除以 100)
            $percentRaw = floatval($data['f170'] ?? 0); // 涨跌幅 (需要除以 100)

            // 根据东财 API 特性，/部分字段需要除以 100
            $price = $priceRaw / 100;
            $change = $changeRaw / 100;
            $percent = $percentRaw / 100;

            // 如果价格为 0，说明数据无效或市场休市
            if ($price == 0) {
                $this->error("{$name} 价格为 0，可能市场休市");
                return null;
            }

            return [
                'price'       => number_format($price, 2),
                'change'      => $change > 0 ? "+".number_format($change, 2) : number_format($change, 2),
                //'percent'     => $percent > 0 ? "+".number_format($percent, 2)."%": number_format($percent, 2)."%",
                'percent'     => abs(number_format($percent, 2))."%",

                'change_raw'  => $change
            ];

        } catch (\Exception $e) {
            $this->error("{$name} 异常：" . $e->getMessage());
            return null;
        }
    }

// -------------------------- 函数 2：download_chart() 下载 K 线图 --------------------------
    private function download_chart($name, $secid, $baseDir, $index) {
        try {
            // 东方财富 K 线图 URL - 修正为单行
            $time = time();
            $chartUrl = "https://webquotepic.eastmoney.com/GetPic.aspx?imageType=r&token=44c9d251add88e27b65ed86506f6e5da&nid={$secid}&timespan={$time}";

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.3.1 Safari/605.1.15',
                'Referer' => 'https://quote.eastmoney.com/',
                'Accept' => 'image/gif,image/png,image/jpeg,*/*'
            ])
                ->withOptions([
                    'version' => 1.1, // 强制HTTP/1.1，解决服务器断开问题
                    'curl' => [
                        CURLOPT_TIMEOUT => 10,
                    ]
                ])
                ->timeout(15)
                ->get($chartUrl);

            // 检查响应状态和内容长度
            if ($response->successful()) {
                $imageContent = $response->body();
                if (!empty($imageContent)) {
                    // 使用带序号的文件名，确保顺序一致
                    $imageName = sprintf("%02d_%s.png", $index, $name);
                    file_put_contents("{$baseDir}/{$imageName}", $imageContent);
                    $this->info("{$name} K 线图下载成功 (大小：" . strlen($imageContent) . " bytes)");
                    return $imageName;
                } else {
                    $this->warn("{$name} K 线图内容为空");
                }
            } else {
                $this->warn("{$name} K 线图下载失败 - HTTP: {$response->status()}");
            }
        } catch (\Exception $e) {
            // 图片下载失败不阻塞流程
            $this->warn("{$name} K 线图异常：" . $e->getMessage());
        }

        return false;
    }

    // -------------------------- 函数 3：生成音频 --------------------------
    private function generate_audio($baseDir, $content) {
        try {
            $this->info("正在生成音频文件...");

            // 使用 Mac 自带的 say 命令生成音频
            // 指定中文语音（Ting-Ting 或 Mei-Jia）
            $voice = 'Lilian'; // 普通话女声
            $outputFileAiff = "{$baseDir}/数据总结.aiff";
            $outputFileMp3 = "{$baseDir}/数据总结.mp3";

            // 构建命令 - 先生成 AIFF 格式
            $command = sprintf(
                'say -v %s -o %s %s',
                escapeshellarg($voice),
                escapeshellarg($outputFileAiff),
                escapeshellarg($content)
            );

            // 执行命令
            exec($command, $output, $returnVar);

            if ($returnVar === 0 && file_exists($outputFileAiff)) {
                // 使用 afconvert 转换为 MP3 格式（AAC 编码，兼容性更好）
                $convertCommand = sprintf(
                    'afconvert -f mp4f -d aac -b 128000 %s %s 2>&1',
                    escapeshellarg($outputFileAiff),
                    escapeshellarg($outputFileMp3)
                );

                exec($convertCommand, $convertOutput, $convertVar);

                if ($convertVar === 0 && file_exists($outputFileMp3)) {
                    // 删除原始 AIFF 文件
                    @unlink($outputFileAiff);

                    $fileSize = filesize($outputFileMp3);
                    $this->info("音频文件生成成功：{$outputFileMp3} (大小：" . number_format($fileSize / 1024, 2) . " KB)");

                    // 获取音频时长
                    $duration = $this->get_audio_duration($outputFileMp3);
                    return ['audio_path' => $outputFileMp3, 'duration' => $duration];
                } else {
                    // 如果转换失败，重命名 AIFF 文件
                    @rename($outputFileAiff, $outputFileMp3);
                    $fileSize = filesize($outputFileMp3);
                    $this->warn("格式转换失败，已保存为 AIFF 格式：{$outputFileMp3} (大小：" . number_format($fileSize / 1024, 2) . " KB)");

                    $duration = $this->get_audio_duration($outputFileMp3);
                    return ['audio_path' => $outputFileMp3, 'duration' => $duration];
                }
            } else {
                // 如果 AI 语音不可用，尝试使用默认语音
                $this->warn("AI 语音不可用，尝试使用系统默认语音...");
                $command = sprintf(
                    'say -o %s %s',
                    escapeshellarg($outputFileAiff),
                    escapeshellarg($content)
                );
                exec($command, $output, $returnVar);

                if ($returnVar === 0 && file_exists($outputFileAiff)) {
                    // 尝试转换格式
                    $convertCommand = sprintf(
                        'afconvert -f mp4f -d aac -b 128000 %s %s 2>&1',
                        escapeshellarg($outputFileAiff),
                        escapeshellarg($outputFileMp3)
                    );

                    exec($convertCommand, $convertOutput, $convertVar);

                    if ($convertVar === 0 && file_exists($outputFileMp3)) {
                        @unlink($outputFileAiff);
                        $fileSize = filesize($outputFileMp3);
                        $this->info("音频文件生成成功（系统默认语音）: {$outputFileMp3} (大小：" . number_format($fileSize / 1024, 2) . " KB)");

                        $duration = $this->get_audio_duration($outputFileMp3);
                        return ['audio_path' => $outputFileMp3, 'duration' => $duration];
                    } else {
                        @rename($outputFileAiff, $outputFileMp3);
                        $fileSize = filesize($outputFileMp3);
                        $this->info("音频文件生成成功（系统默认语音，AIFF 格式）: {$outputFileMp3} (大小：" . number_format($fileSize / 1024, 2) . " KB)");

                        $duration = $this->get_audio_duration($outputFileMp3);
                        return ['audio_path' => $outputFileMp3, 'duration' => $duration];
                    }
                } else {
                    $this->error("音频文件生成失败");
                    return null;
                }
            }

        } catch (\Exception $e) {
            $this->error("生成音频时出错：" . $e->getMessage());
            return null;
        }
    }

    // -------------------------- 函数 3.5：分段生成音频 --------------------------
    private function generate_segmented_audio($baseDir, $successData) {
        try {
            $this->info("正在分段生成音频文件...");

            $voice = 'Lilian';
            $audioSegments = [];
            $totalDuration = 0;

            foreach ($successData as $index => $item) {
                $text = $item['line'];
                $segIndex = $item['index'];

                $this->info("生成第 {$segIndex} 段音频：{$item['name']}");

                // 生成本段音频
                $segmentFile = "{$baseDir}/audio_{$segIndex}.aiff";
                $command = sprintf(
                    'say -v %s -o %s %s',
                    escapeshellarg($voice),
                    escapeshellarg($segmentFile),
                    escapeshellarg($text)
                );

                exec($command, $output, $returnVar);

                if ($returnVar === 0 && file_exists($segmentFile)) {
                    // 获取本段时长
                    $duration = $this->get_audio_duration($segmentFile);

                    $audioSegments[] = [
                        'path' => $segmentFile,
                        'duration' => $duration,
                        'index' => $segIndex,
                        'name' => $item['name'],
                        'line' => $item['line'],
                        'secId' => $item['secId'],
                    ];

                    $totalDuration += $duration;
                    $this->info("第 {$segIndex} 段音频生成成功，时长：" . round($duration, 2) . " 秒");
                } else {
                    $this->error("第 {$segIndex} 段音频生成失败");
                }

                usleep(100000); // 0.1 秒延迟
            }

            if (empty($audioSegments)) {
                $this->error("所有音频片段生成失败");
                return null;
            }

            // 合并所有音频片段
            $this->info("正在合并 " . count($audioSegments) . " 个音频片段...");

            $finalMp3 = "{$baseDir}/数据总结.mp3";

            // 创建临时文件列表用于 ffmpeg concat demuxer
            $listFile = tempnam(sys_get_temp_dir(), 'ffmpeg_audio_');
            $listContent = "";

            foreach ($audioSegments as $seg) {
                // 确保路径使用绝对路径并正确处理特殊字符
                $absolutePath = realpath($seg['path']);
                if ($absolutePath) {
                    $listContent .= "file '" . str_replace("'", "'\\''", $absolutePath) . "'\n";
                }
            }
            file_put_contents($listFile, $listContent);

            $this->info("音频文件列表内容:\n" . trim($listContent));

            // 使用 ffmpeg concat demuxer 合并音频（更安全的方式）
            $mergeCommand = sprintf(
                'ffmpeg -y -f concat -safe 0 -i %s -c:a libmp3lame -b:a 128k %s 2>&1',
                escapeshellarg($listFile),
                escapeshellarg($finalMp3)
            );

            $this->info("执行合并命令：" . $mergeCommand);
            exec($mergeCommand, $mergeOutput, $mergeVar);

            // 输出合并日志
            if (!empty($mergeOutput)) {
                $this->info("合并输出：" . implode("\n", $mergeOutput));
            }

            // 清理临时文件
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
                $this->error("音频合并失败，返回码：{$mergeVar}");

                // 尝试备用方案：使用 cat 直接合并 AIFF 文件
                $this->warn("尝试使用备用方案合并音频...");
                return $this->merge_audio_with_cat($audioSegments, $baseDir, $totalDuration);
            }

        } catch (\Exception $e) {
            $this->error("分段生成音频时出错：" . $e->getMessage());
            return null;
        }
    }

    // -------------------------- 函数 3.6：备用音频合并方案 --------------------------
    private function merge_audio_with_cat($audioSegments, $baseDir, $totalDuration) {
        try {
            $finalAiff = "{$baseDir}/数据总结.aiff";
            $finalMp3 = "{$baseDir}/数据总结.mp3";

            // 使用 SoX 工具合并（如果已安装）
            $inputFiles = implode(' ', array_map(function($seg) {
                return escapeshellarg($seg['path']);
            }, $audioSegments));

            $soxCommand = "sox {$inputFiles} {$finalAiff} 2>&1";
            exec($soxCommand, $soxOutput, $soxVar);

            if ($soxVar === 0 && file_exists($finalAiff)) {
                // 转换为 MP3
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

            // 如果 SoX 也不可用，直接使用第一个音频文件作为备选
            $this->warn("所有合并方案均失败，使用第一段音频作为备选");
            if (!empty($audioSegments) && file_exists($audioSegments[0]['path'])) {
                $firstSeg = $audioSegments[0];
                $convertCommand = sprintf(
                    'afconvert -f mp4f -d aac -b 128000 %s %s 2>&1',
                    escapeshellarg($firstSeg['path']),
                    escapeshellarg($finalMp3)
                );
                exec($convertCommand, $convertOutput, $convertVar);

                if ($convertVar === 0 && file_exists($finalMp3)) {
                    foreach ($audioSegments as $seg) {
                        @unlink($seg['path']);
                    }

                    $this->warn("已使用第一段音频作为备选输出");
                    return [
                        'audio_path' => $finalMp3,
                        'duration' => $firstSeg['duration'],
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

    // -------------------------- 函数 4：获取音频时长 --------------------------
    private function get_audio_duration($audioPath) {
        try {
            // 使用 ffprobe 获取音频时长
            $command = sprintf(
                'ffprobe -i %s -show_entries format=duration -v quiet -of csv="p=0" 2>&1',
                escapeshellarg($audioPath)
            );

            exec($command, $output, $returnVar);

            if ($returnVar === 0 && !empty($output)) {
                $duration = floatval(trim($output[0]));
                $this->info("音频时长：" . round($duration, 2) . " 秒");
                return $duration;
            }

            // 如果 ffprobe 失败，尝试使用 afinfo (macOS 自带)
            $afCommand = sprintf(
                'afinfo %s 2>&1 | grep "duration:" | awk \'{print $2}\'',
                escapeshellarg($audioPath)
            );

            exec($afCommand, $afOutput, $afVar);

            if ($afVar === 0 && !empty($afOutput)) {
                $duration = floatval(trim($afOutput[0]));
                $this->info("音频时长：" . round($duration, 2) . " 秒");
                return $duration;
            }

            $this->warn("无法获取音频时长，使用默认值 60 秒");
            return 60.0;

        } catch (\Exception $e) {
            $this->error("获取音频时长失败：" . $e->getMessage());
            return 60.0;
        }
    }

    // -------------------------- 函数 5：生成视频 --------------------------
    private function generate_video($baseDir, $audioInfo, $successData = []) {
        try {
            $audioPath = $audioInfo['audio_path'];
            $segments = $audioInfo['segments'] ?? [];

            $this->info("正在生成视频文件...");

            // 如果有分段音频信息，使用精确的时长匹配
            if (!empty($segments) && !empty($successData)) {
                $this->info("使用分段音频时长生成视频，共 " . count($segments) . " 段");

                $date = date('Ymd');
                $outputVideo = "{$baseDir}/{$date}隔夜全球市场资讯,A股开盘参考.mp4";
                $tempFile = tempnam(sys_get_temp_dir(), 'ffmpeg_');
                $listContent = "";

                foreach ($segments as $seg) {
                    $index = $seg['index'];
                    $duration = $seg['duration'];
                    $name = $seg['name'];
                    $line = $seg['line'];
                    $secId = $seg['secId'];
                    if(array_key_exists($secId, $this->noIndexSecid)){
                        $line .= '(' . $this->noIndexSecid[$secId] . ')';
                    }

                    // 找到对应的图片
                    $imagePath = null;
                    foreach ($successData as $item) {
                        if ($item['index'] === $index) {
                            $imagePath = $baseDir . '/' . $item['image'];
                            break;
                        }
                    }

                    if (!$imagePath || !file_exists($imagePath)) {
                        $this->warn("找不到对应的图片：索引 #{$index}");
                        continue;
                    }

                    // 为每张图片创建视频片段（使用对应音频的时长）
                    $clipFile = "{$baseDir}/clip_{$index}.mp4";

                    // 先用 ImageMagick 在图片上添加文字
                    $imageWithText = "{$baseDir}/image_{$index}_with_text.png";

                    // 使用 composite 方式在图片上叠加文字层
                    // 先生成文字图片，再合成到原图上
                    $textImage = "{$baseDir}/text_{$index}.png";

                    // 步骤 1: 创建透明背景的文字图片（使用 PNG32 格式确保只生成一个文件）
                    $createTextCmd = sprintf(
                        'magick xc:none -size 1920x80 -font "%s" -pointsize 40 -fill black -stroke black -strokewidth 1 -gravity center caption:"%s" png32:%s 2>&1',
                        "/System/Library/Fonts/STHeiti Medium.ttc",
                        addslashes($line),
                        escapeshellarg($textImage)
                    );

                    $this->info("创建文字图层...");
                    $this->info("执行命令：" . $createTextCmd);
                    exec($createTextCmd, $textOutput, $textReturn);

                    // 步骤 2: 将文字图层合成到原图底部
                    if ($textReturn === 0) {
                        $compositeCmd = sprintf(
                            'magick %s -resize 1920x1040 -gravity center -background black -extent 1920x1080 %s -gravity South -geometry +0+0 -compose over -composite %s 2>&1',
                            escapeshellarg($imagePath),
                            escapeshellarg("{$baseDir}/text_{$index}-1.png"),
                            escapeshellarg($imageWithText)
                        );

                        $this->info("合成文字到图片:" . $compositeCmd);
                        exec($compositeCmd, $compOutput, $compReturn);

                        @unlink($textImage);
                        @unlink("{$baseDir}/text_{$index}-0.png");
                        @unlink("{$baseDir}/text_{$index}-1.png");

                        if ($compReturn !== 0 || !file_exists($imageWithText)) {
                            $this->warn("图片合成失败，返回码：" . $compReturn);
                            $this->warn("错误输出：" . implode("\n", $compOutput));
                            $finalImage = $imagePath;
                        } else {
                            $this->info("✓ 图片文字添加成功");
                            $finalImage = $imageWithText;
                        }
                    } else {
                        $this->warn("文字图层创建失败");
                        $this->warn("错误输出：" . implode("\n", $textOutput));
                        $finalImage = $imagePath;
                        @unlink($textImage);
                    }

                    // 使用 ffmpeg 将图片转为 1080p 视频片段
                    $command = sprintf(
                        'ffmpeg -y -loop 1 -i %s -c:v h264 -t %f -vf "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2" -pix_fmt yuv420p -r 30 %s 2>&1',
                        escapeshellarg($finalImage),
                        $duration,
                        escapeshellarg($clipFile)
                    );

                    $this->info("处理图片 #{$index}: {$name} (时长：" . round($duration, 2) . "秒)");
                    exec($command, $output, $returnVar);

                    if ($returnVar === 0 && file_exists($clipFile)) {
                        $listContent .= "file '" . str_replace("'", "'\\''", $clipFile) . "'\n";
                        $this->info("✓ 视频片段创建成功：{$name}");

                        // 清理临时图片
                        @unlink($imageWithText);
                    } else {
                        $this->warn("创建视频片段失败：{$name} . {$command}");
                        $this->warn("错误输出：" . implode("\n", $output));
                        @unlink($imageWithText);
                    }
                }

                // 写入文件列表
                file_put_contents($tempFile, $listContent);

                // 使用 concat demuxer 合并所有视频片段并添加音频
                $mergeCommand = sprintf(
                    'ffmpeg -y -f concat -safe 0 -i %s -i %s -c:v copy -c:a aac -b:a 128k -shortest %s 2>&1',
                    escapeshellarg($tempFile),
                    escapeshellarg($audioPath),
                    escapeshellarg($outputVideo)
                );

                exec($mergeCommand, $mergeOutput, $mergeVar);

                // 清理临时文件
                @unlink($tempFile);
                foreach ($segments as $seg) {
                    @unlink("{$baseDir}/clip_{$seg['index']}.mp4");
                }

                if ($mergeVar === 0 && file_exists($outputVideo)) {
                    $fileSize = filesize($outputVideo);
                    $videoDuration = $this->get_video_duration($outputVideo);
                    $this->info("视频文件生成成功：{$outputVideo}");
                    $this->info("视频时长：" . round($videoDuration, 2) . " 秒，大小：" . number_format($fileSize / 1024 / 1024, 2) . " MB");
                    return true;
                } else {
                    $this->error("视频合并失败");
                    $this->error("合并输出：" . implode("\n", $mergeOutput));
                    return false;
                }
            }

            // 兼容旧版本：如果没有分段信息，使用原来的逻辑
            $this->warn("未找到分段音频信息，使用传统方式生成视频");

            // 如果有 successData，直接使用它来保证顺序一致
            if (!empty($successData)) {
                $this->info("使用成功数据的顺序生成视频，共 " . count($successData) . " 张图片");

                $outputVideo = "{$baseDir}/全球市场复盘.mp4";

                // 创建临时文件列表用于 ffmpeg concat demuxer
                $tempFile = tempnam(sys_get_temp_dir(), 'ffmpeg_');
                $listContent = "";

                // 计算每张图片的显示时间
                $totalDuration = $audioInfo['duration'];
                $imageDuration = $totalDuration / count($successData);
                $this->info("每张图片显示时长：" . round($imageDuration, 2) . " 秒");

                foreach ($successData as $item) {
                    $imagePath = $baseDir . '/' . $item['image'];
                    $index = $item['index'];
                    $name = $item['name'];

                    if (!file_exists($imagePath)) {
                        $this->warn("图片不存在：{$imagePath}");
                        continue;
                    }

                    // 为每张图片创建视频片段（1080p 分辨率）
                    $clipFile = "{$baseDir}/clip_{$index}.mp4";

                    // 使用 ffmpeg 将图片转为 1080p 视频片段，保持比例并添加黑边
                    $command = sprintf(
                        'ffmpeg -y -loop 1 -i %s -c:v h264 -t %f -vf "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2:color=black" -pix_fmt yuv420p -r 30 %s 2>&1',
                        escapeshellarg($imagePath),
                        $imageDuration,
                        escapeshellarg($clipFile)
                    );

                    $this->info("处理图片 #{$index}: {$name} (" . basename($imagePath) . ")");
                    exec($command, $output, $returnVar);

                    if ($returnVar === 0 && file_exists($clipFile)) {
                        $listContent .= "file '" . str_replace("'", "'\\''", $clipFile) . "'\n";
                    } else {
                        $this->warn("创建视频片段失败：{$name}");
                    }
                }

                // 写入文件列表
                file_put_contents($tempFile, $listContent);

                // 使用 concat demuxer 合并所有视频片段并添加音频
                $mergeCommand = sprintf(
                    'ffmpeg -y -f concat -safe 0 -i %s -i %s -c:v copy -c:a aac -b:a 128k -shortest %s 2>&1',
                    escapeshellarg($tempFile),
                    escapeshellarg($audioPath),
                    escapeshellarg($outputVideo)
                );

                exec($mergeCommand, $mergeOutput, $mergeVar);

                // 清理临时文件
                @unlink($tempFile);
                foreach ($successData as $item) {
                    @unlink("{$baseDir}/clip_{$item['index']}.mp4");
                }

                if ($mergeVar === 0 && file_exists($outputVideo)) {
                    $fileSize = filesize($outputVideo);
                    $videoDuration = $this->get_video_duration($outputVideo);
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

    // -------------------------- 函数 6：获取视频时长 --------------------------
    private function get_video_duration($videoPath) {
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

