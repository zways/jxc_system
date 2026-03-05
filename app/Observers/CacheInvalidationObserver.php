<?php

namespace App\Observers;

use App\Services\CacheService;
use Illuminate\Database\Eloquent\Model;

/**
 * 缓存失效观察者 — 当模型数据变更时自动清除相关 Redis 缓存。
 *
 * 通过 MODEL_TAG_MAP 映射模型类到缓存标签。
 * 当模型触发 saved / deleted / restored 事件时，清除该标签下对应企业的所有缓存。
 */
class CacheInvalidationObserver
{
    /**
     * 模型类 → 缓存标签映射
     */
    private const MODEL_TAG_MAP = [
        \App\Models\Product::class         => [CacheService::TAG_PRODUCTS],
        \App\Models\ProductCategory::class  => [CacheService::TAG_CATEGORIES, CacheService::TAG_PRODUCTS],
        \App\Models\Customer::class         => [CacheService::TAG_CUSTOMERS],
        \App\Models\Supplier::class         => [CacheService::TAG_SUPPLIERS],
        \App\Models\Warehouse::class        => [CacheService::TAG_WAREHOUSES],
        \App\Models\Role::class             => [CacheService::TAG_ROLES, CacheService::TAG_PERMISSIONS],
        \App\Models\Unit::class             => [CacheService::TAG_UNITS],
        \App\Models\Department::class       => [CacheService::TAG_DEPARTMENTS],
    ];

    public function saved(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidate($model);
    }

    /**
     * 清除模型关联的缓存标签
     */
    private function invalidate(Model $model): void
    {
        $modelClass = get_class($model);
        $tags = self::MODEL_TAG_MAP[$modelClass] ?? [];

        if (empty($tags)) {
            return;
        }

        // 获取企业 ID（优先从模型上取，避免跨企业操作时清错缓存）
        $tenantId = $model->store_id ?? null;

        foreach ($tags as $tag) {
            try {
                CacheService::flushTag($tag, $tenantId);
            } catch (\Throwable) {
                // 缓存清除失败不应影响业务
            }
        }
    }
}
