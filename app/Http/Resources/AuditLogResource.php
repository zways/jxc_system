<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name ?: '系统',
            'action' => $this->action,
            'action_label' => $this->actionLabel(),
            'model_type' => $this->model_type,
            'model_type_label' => $this->modelTypeLabel(),
            'model_id' => $this->model_id,
            'model_label' => $this->model_label,
            'store_id' => $this->store_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'description' => $this->description ?: $this->autoDescription(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }

    private function actionLabel(): string
    {
        return match ($this->action) {
            'create' => '创建',
            'update' => '更新',
            'delete' => '删除',
            'restore' => '恢复',
            'void' => '作废',
            'pay' => '付款',
            'collect' => '收款',
            'process' => '处理',
            'login' => '登录',
            'logout' => '登出',
            default => $this->action,
        };
    }

    private function modelTypeLabel(): ?string
    {
        if (!$this->model_type) return null;
        return self::MODEL_TYPE_MAP[$this->model_type] ?? class_basename($this->model_type);
    }

    /**
     * 为历史空描述记录自动生成可读描述（fallback）。
     */
    private function autoDescription(): ?string
    {
        $typeLabel = $this->modelTypeLabel();
        if (!$typeLabel) {
            return null;
        }

        $actionLabel = $this->actionLabel();
        $label = $this->model_label ?? ('#' . $this->model_id);
        $desc = "{$actionLabel}了{$typeLabel} [{$label}]";

        // 对更新操作，尝试从 new_values 中提取变更字段
        if ($this->action === 'update' && is_array($this->new_values)) {
            $changedFields = array_keys($this->new_values);
            $changedFields = array_filter($changedFields, fn($f) => !in_array($f, ['password', 'token_hash', 'remember_token', 'updated_at', 'created_at']));
            if (!empty($changedFields)) {
                $desc .= '，变更字段: ' . implode(', ', $changedFields);
            }
        }

        return $desc;
    }

    private const MODEL_TYPE_MAP = [
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
        'App\Models\AccountsPayable' => '应付',
        'App\Models\AccountsReceivable' => '应收',
        'App\Models\InventoryAdjustment' => '库存调整',
        'App\Models\InventoryCount' => '盘点',
        'App\Models\InventoryTransaction' => '库存流水',
        'App\Models\SalesReturn' => '退货',
        'App\Models\ExchangeRecord' => '换货',
        'App\Models\BusinessAgent' => '业务员',
        'App\Models\ProductCategory' => '商品分类',
        'App\Models\Unit' => '计量单位',
    ];
}
