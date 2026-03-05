<?php

namespace App\Observers;

use App\Jobs\WriteAuditLog;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * 通用审计观察者 — 自动记录模型的 created / updated / deleted / restored 事件。
 *
 * 使用方式：在 AppServiceProvider::boot() 中注册到需要审计的模型。
 *
 * 性能优化（第二阶段）：审计日志通过队列异步写入 Redis → Worker 消费 → 写入 DB，
 * 避免在业务请求中同步写入审计表。
 */
class AuditObserver
{
    /**
     * 模型类 → 中文名称映射
     */
    private const MODEL_TYPE_LABELS = [
        'App\Models\PurchaseOrder' => '采购单',
        'App\Models\SalesOrder' => '销售单',
        'App\Models\Customer' => '客户',
        'App\Models\Supplier' => '供应商',
        'App\Models\Product' => '商品',
        'App\Models\Warehouse' => '仓库',
        'App\Models\Department' => '部门',
        'App\Models\Store' => '门店',
        'App\Models\User' => '用户',
        'App\Models\Role' => '角色',
        'App\Models\FinancialTransaction' => '财务流水',
        'App\Models\AccountPayable' => '应付',
        'App\Models\AccountReceivable' => '应收',
        'App\Models\InventoryAdjustment' => '库存调整',
        'App\Models\InventoryCount' => '盘点',
        'App\Models\InventoryTransaction' => '库存流水',
        'App\Models\SalesReturn' => '退货',
        'App\Models\ExchangeRecord' => '换货',
        'App\Models\BusinessAgent' => '业务员',
        'App\Models\ProductCategory' => '商品分类',
        'App\Models\Unit' => '计量单位',
    ];

    /**
     * 操作类型 → 中文标签
     */
    private const ACTION_LABELS = [
        'create' => '创建',
        'update' => '更新',
        'delete' => '删除',
        'restore' => '恢复',
    ];

    public function created(Model $model): void
    {
        $this->log('create', $model, null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        $original = array_intersect_key($model->getOriginal(), $changes);

        // 过滤掉仅 updated_at 的变更
        unset($changes['updated_at'], $original['updated_at']);
        if (empty($changes)) {
            return;
        }

        $this->log('update', $model, $original, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->log('delete', $model, $model->getOriginal(), null);
    }

    public function restored(Model $model): void
    {
        $this->log('restore', $model, null, null);
    }

    // ------------------------------------------------------------------

    private function log(string $action, Model $model, ?array $oldValues, ?array $newValues): void
    {
        // 避免审计日志自身递归
        if ($model instanceof AuditLog) {
            return;
        }

        $user = Auth::user();
        $request = request();

        // 自动生成可读标签
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

        // 过滤敏感字段
        $sensitiveKeys = ['password', 'token_hash', 'remember_token'];
        $filter = function (?array $values) use ($sensitiveKeys): ?array {
            if ($values === null) {
                return null;
            }
            foreach ($sensitiveKeys as $key) {
                if (array_key_exists($key, $values)) {
                    $values[$key] = '******';
                }
            }
            return $values;
        };

        // 自动生成操作描述
        $description = $this->buildDescription($action, $model, $newValues);

        // 当无认证用户时，标记为"系统"
        $userName = $user?->real_name ?? $user?->name ?? $user?->username;
        if ($userName === null) {
            $userName = '系统';
        }

        $logData = [
            'user_id' => $user?->id,
            'user_name' => $userName,
            'action' => $action,
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'model_label' => $modelLabel,
            'store_id' => $model->store_id ?? $user?->store_id ?? null,
            'old_values' => $filter($oldValues),
            'new_values' => $filter($newValues),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'description' => $description,
            'created_at' => now()->toDateTimeString(),
        ];

        try {
            // 异步写入：通过队列派发，减少请求延迟
            WriteAuditLog::dispatch($logData)->onQueue('audit');
        } catch (\Throwable) {
            // 队列派发失败时回退到同步写入，保证审计数据不丢失
            try {
                AuditLog::create($logData);
            } catch (\Throwable) {
                // 审计写入失败不应影响业务流程
            }
        }
    }

    /**
     * 根据操作类型、模型和变更字段自动生成可读的操作描述。
     */
    private function buildDescription(string $action, Model $model, ?array $newValues): string
    {
        $modelClass = get_class($model);
        $typeLabel = self::MODEL_TYPE_LABELS[$modelClass] ?? class_basename($modelClass);
        $actionLabel = self::ACTION_LABELS[$action] ?? $action;

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

        $desc = "{$actionLabel}了{$typeLabel} [{$modelLabel}]";

        // 对于更新操作，附加变更的字段名称
        if ($action === 'update' && $newValues) {
            $changedFields = array_keys($newValues);
            // 过滤掉敏感字段名和时间戳
            $changedFields = array_filter($changedFields, fn($f) => !in_array($f, ['password', 'token_hash', 'remember_token', 'updated_at', 'created_at']));
            if (!empty($changedFields)) {
                $desc .= '，变更字段: ' . implode(', ', $changedFields);
            }
        }

        return $desc;
    }
}
