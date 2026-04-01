<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BiliUpload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bili_upload';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '将生成的视频自动上传到 B 站';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // ===================== 配置区域 =====================
        // 你的 Mac 用户名
        $user = 'cheniewiew';

        // B 站分区 ID（财经板块）
        $tid = 188;  // 财经 -> 金融财经

                // 是否开启原创
        $copyright = 1; // 1=原创，2=转载

        // ===================================================

        $date = date('Ymd');
        $baseDir = "/Users/$user/Desktop/隔夜全球市场数据_$date";

        if (!is_dir($baseDir)) {
            $this->error("目录不存在：{$baseDir}");
            return 1;
        }

        // 视频封面模式：auto(自动生成) 或 指定图片路径
        $cover = "/Users/$user/Desktop/隔夜全球市场数据_$date/02_布伦特原油.png";//todo

        // 查找视频文件
        $videoFile = null;
        //$files = "{$baseDir}/{$date}隔夜全球市场资讯,A股开盘参考.mp4";
        $files = glob("{$baseDir}/{$date}隔夜全球市场资讯,A股开盘参考.mp4");

        if (!empty($files)) {
            $videoFile = $files[0];
        } else {
            // 尝试其他命名模式
            $files = glob("{$baseDir}/*.mp4");
            if (!empty($files)) {
                $videoFile = $files[0];
            }
        }

        if (!$videoFile || !file_exists($videoFile)) {
            $this->error("未找到视频文件");
            return 1;
        }

        $this->info("找到视频文件：{$videoFile}");

        // 设置默认标题和简介
        $title = date('Y 年 m 月 d 日') . '隔夜全球市场资讯，A 股开盘参考';
        //$title = $this->option('title') ?: $defaultTitle;

        $desc = "更新时间：{$date}\n\n" .
            "内容包含：\n" .
            "1. 富时中国 A50\n" .
            "2. 美股三大指数（道琼斯、纳斯达克、标普 500）\n" .
            "3. 美股重要板块\n" .
            "4. 欧洲主要股指\n" .
            "5. 亚太市场\n" .
            //"6. 大宗商品与汇率\n\n" .
            "数据来源：东方财富网";
        //$desc = $this->option('desc') ?: $defaultDesc;

        $tags = '财经,股票,美股,A股,投资,金融,股市,原油,黄金';
        //$tags = $this->option('tags') ?: $defaultTags;

        // 确认是否上传
        /*if (!$this->confirm('确认要上传这个视频到 B 站吗？')) {
            $this->warn('已取消上传');
            return 0;
        }*/

        // 检查 biliup 是否安装
        $this->info("检查 biliup 是否安装...");
        exec('which biliup', $output, $returnVar);

        if ($returnVar !== 0) {
            $this->error("未找到 biliup 命令");
            $this->warn("请先安装 biliup：pip3 install biliup");
            $this->warn("或者使用其他方式登录：php artisan bilibili:login");
            return 1;
        }

        $this->info("✓ biliup 已安装");

        // 构建上传命令（使用正确的参数格式）
        $uploadCmd = sprintf(
            'biliup upload --title %s --desc %s --tag %s --tid %d --copyright %d --cover %s %s 2>&1',
            escapeshellarg($title),
            escapeshellarg($desc),
            escapeshellarg($tags),
            $tid,
            $copyright,
            $cover,
            escapeshellarg($videoFile)
        );

        $this->info("开始上传视频...");
        $this->info("命令：{$uploadCmd}");

        exec($uploadCmd, $output, $returnVar);

        if ($returnVar === 0) {
            $this->info("✓ 视频上传成功！");
            foreach ($output as $line) {
                if (strpos($line, 'http') !== false) {
                    $this->info("视频链接：{$line}");
                }
            }
            return 0;
        } else {
            $this->error("视频上传失败");
            $this->error("错误输出：" . implode("\n", $output));

            if (strpos(implode("\n", $output), 'cookie') !== false ||
                strpos(implode("\n", $output), '登录') !== false) {
                $this->warn("可能需要先登录 B 站账号");
                $this->info("请运行：php artisan bilibili:login");
            }

            return 1;
        }
    }
}
