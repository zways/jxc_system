<?php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * 异步写入审计日志 — 将审计日志写入从同步改为队列异步，减少请求延迟。
 *
 * 特点：
 * - 使用 Redis 队列，失败不影响业务
 * - 最多重试 3 次，3 次后丢弃（审计日志非关键路径）
 * - 超时 30 秒
 */
class WriteAuditLog implements ShouldQueue
{
    use Queueable;

    /** 最大重试次数 */
    public int $tries = 3;

    /** 任务超时（秒） */
    public int $timeout = 30;

    /** 重试延迟（秒）—— 指数退避 */
    public array $backoff = [5, 15, 30];

    /**
     * @param array $logData 审计日志数据（AuditLog::create() 需要的字段）
     */
    public function __construct(
        private readonly array $logData,
    ) {}

    public function handle(): void
    {
        try {
            AuditLog::create($this->logData);
        } catch (\Throwable $e) {
            Log::warning('[AuditLog] 异步写入审计日志失败', [
                'error' => $e->getMessage(),
                'data' => array_diff_key($this->logData, array_flip(['old_values', 'new_values'])),
            ]);
            throw $e; // 重新抛出以便队列重试
        }
    }

    /**
     * 所有重试耗尽后的处理
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[AuditLog] 异步写入审计日志最终失败', [
            'error' => $exception->getMessage(),
            'model_type' => $this->logData['model_type'] ?? 'unknown',
            'model_id' => $this->logData['model_id'] ?? 'unknown',
            'action' => $this->logData['action'] ?? 'unknown',
        ]);
    }
}
