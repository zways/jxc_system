<?php

namespace App\Scopes;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * 企业全局作用域
 *
 * 自动为所有查询添加 store_id 过滤条件，确保数据隔离。
 *
 * 行为规则：
 * 1. 企业上下文未激活（CLI/队列/Seeder）→ 不过滤
 * 2. 超级管理员 → 不过滤
 * 3. 有企业 ID → 按 store_id 过滤
 * 4. 上下文已激活但无企业 ID → 返回空结果（安全兜底）
 */
class TenantScope implements Scope
{
    /**
     * 将约束应用到给定的 Eloquent 查询构建器
     */
    public function apply(Builder $builder, Model $model): void
    {
        // 1. 未激活（CLI、队列、Seeder 等环境）→ 不过滤
        if (!TenantContext::isActive()) {
            return;
        }

        // 2. 超级管理员 → 不过滤，可以看到所有数据
        if (TenantContext::isSuperAdmin()) {
            return;
        }

        // 3. 有企业 ID → 过滤
        $tenantId = TenantContext::getTenantId();
        if ($tenantId !== null) {
            $builder->where($model->getTable() . '.store_id', $tenantId);
            return;
        }

        // 4. 上下文已激活但无企业 ID → 安全兜底，返回空
        $builder->whereRaw('1 = 0');
    }
}
