<?php

namespace App\Traits;

use App\Models\Store;
use App\Scopes\TenantScope;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 多企业数据隔离 Trait
 *
 * 功能：
 * 1. 自动注册 TenantScope 全局作用域 → 查询自动按 store_id 过滤
 * 2. 创建记录时自动填充 store_id → 无需手动赋值
 * 3. 提供 tenant() 关联方法 → 方便获取所属企业
 *
 * 使用方法：
 *   在有 store_id 字段的模型中 use BelongsToTenant;
 */
trait BelongsToTenant
{
    /**
     * Boot trait: 注册全局作用域 & creating 事件
     */
    public static function bootBelongsToTenant(): void
    {
        // 注册全局作用域
        static::addGlobalScope(new TenantScope);

        // 创建记录时自动填充 store_id
        static::creating(function (Model $model) {
            // 只在企业上下文激活且非超级管理员时自动填充
            if (TenantContext::isActive() && !TenantContext::isSuperAdmin()) {
                $tenantId = TenantContext::getTenantId();
                if ($tenantId !== null && empty($model->store_id)) {
                    $model->store_id = $tenantId;
                }
            }
        });
    }

    /**
     * 所属企业（门店/企业）
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
