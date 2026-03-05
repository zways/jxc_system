<?php

namespace App\Traits;

use App\Jobs\WriteAuditLog;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait Auditable
{
    /**
     * 记录审计日志（异步写入队列）
     *
     * @param Request     $request    当前请求
     * @param string      $action     操作类型 (create, update, delete, restore, void, pay, collect, process, login, logout)
     * @param Model|null  $model      操作对象
     * @param array|null  $oldValues  变更前的值
     * @param array|null  $newValues  变更后的值
     * @param string|null $description 操作描述
     */
    protected function audit(
        Request $request,
        string $action,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
    ): void {
        $user = $request->user();

        // 自动生成可读标签
        $modelLabel = null;
        if ($model) {
            $modelLabel = $model->order_number
                ?? $model->transaction_number
                ?? $model->store_code
                ?? $model->customer_code
                ?? $model->supplier_code
                ?? $model->agent_code
                ?? $model->code
                ?? $model->name
                ?? $model->username
                ?? ('#' . $model->getKey());
        }

        // 过滤敏感字段
        $sensitiveKeys = ['password', 'token_hash', 'remember_token'];
        $filterSensitive = function (?array $values) use ($sensitiveKeys): ?array {
            if ($values === null) return null;
            foreach ($sensitiveKeys as $key) {
                if (array_key_exists($key, $values)) {
                    $values[$key] = '******';
                }
            }
            return $values;
        };

        $logData = [
            'user_id' => $user?->id,
            'user_name' => $user?->real_name ?? $user?->name ?? $user?->username,
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->getKey(),
            'model_label' => $modelLabel,
            'store_id' => $model?->store_id ?? $user?->store_id,
            'old_values' => $filterSensitive($oldValues),
            'new_values' => $filterSensitive($newValues),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'description' => $description,
            'created_at' => now()->toDateTimeString(),
        ];

        try {
            // 当队列驱动为 sync 时直接同步执行；
            // 当队列驱动为 database/redis 等异步驱动时，尝试派发到队列。
            // 若队列 worker 未运行（database 驱动下 dispatch 本身不会失败，
            // 只是 job 留在 jobs 表中），为确保审计日志可靠写入，
            // 对 database 驱动也采用同步写入。
            $queueConnection = config('queue.default');
            if ($queueConnection === 'sync' || $queueConnection === 'database') {
                AuditLog::create($logData);
            } else {
                WriteAuditLog::dispatch($logData)->onQueue('audit');
            }
        } catch (\Throwable) {
            // 队列派发失败时回退到同步写入
            try {
                AuditLog::create($logData);
            } catch (\Throwable) {
                // 审计写入失败不应影响业务流程
            }
        }
    }

    /**
     * 记录模型创建日志
     */
    protected function auditCreated(Request $request, Model $model, ?string $description = null): void
    {
        $this->audit($request, 'create', $model, null, $model->toArray(), $description);
    }

    /**
     * 记录模型更新日志（自动 diff 变更字段）
     */
    protected function auditUpdated(Request $request, Model $model, array $originalAttributes, ?string $description = null): void
    {
        $changes = $model->getChanges();
        $old = array_intersect_key($originalAttributes, $changes);
        unset($changes['updated_at']);
        unset($old['updated_at']);
        if (empty($changes)) return;
        $this->audit($request, 'update', $model, $old, $changes, $description);
    }

    /**
     * 记录模型删除日志
     */
    protected function auditDeleted(Request $request, Model $model, ?string $description = null): void
    {
        $this->audit($request, 'delete', $model, $model->toArray(), null, $description);
    }
}
