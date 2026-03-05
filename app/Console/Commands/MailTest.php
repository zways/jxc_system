<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MailTest extends Command
{
    protected $signature = 'mail:test
        {to : 收件人邮箱地址}
        {--subject=测试邮件 : 邮件主题}';

    protected $description = '发送一封测试邮件，用于验证 SMTP/邮件服务配置是否正确';

    public function handle(): int
    {
        $to = $this->argument('to');
        $subject = $this->option('subject');

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('请提供有效的邮箱地址');
            return self::FAILURE;
        }

        $this->info("正在向 {$to} 发送测试邮件（主题：{$subject}）…");

        try {
            Mail::raw(
                "这是一封来自 " . config('app.name') . " 的测试邮件。\n\n如果您能收到此邮件，说明邮件服务配置正确。\n\n发送时间：" . now()->format('Y-m-d H:i:s'),
                function ($message) use ($to, $subject) {
                    $message->to($to)->subject($subject);
                }
            );
            $this->info('发送成功，请到收件箱（或垃圾邮件）中查收。');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('发送失败：' . $e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }
}
