<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * 统一缓存服务 — 为高频读取的业务数据提供 Redis 缓存层。
 *
 * 设计原则：
 * 1. 所有缓存 key 都带企业前缀，保证多企业隔离
 * 2. 提供统一的 remember / forget / flush 语义
 * 3. 通过 Model Observer 自动失效（见 CacheInvalidationObserver）
 */
class CacheService
{
    // ── TTL 常量（秒） ───────────────────────────

    /** 短期缓存：5 分钟（适用于列表页、下拉选项等） */
    public const TTL_SHORT = 300;

    /** 中期缓存：30 分钟（适用于商品详情、分类树等） */
    public const TTL_MEDIUM = 1800;

    /** 长期缓存：2 小时（适用于权限、系统配置等低频变更数据） */
    public const TTL_LONG = 7200;

    /** 超长缓存：24 小时（适用于几乎不变的字典数据） */
    public const TTL_DAY = 86400;

    // ── 缓存标签前缀 ───────────────────────────

    public const TAG_PERMISSIONS = 'permissions';
    public const TAG_PRODUCTS    = 'products';
    public const TAG_CATEGORIES  = 'categories';
    public const TAG_CUSTOMERS   = 'customers';
    public const TAG_SUPPLIERS   = 'suppliers';
    public const TAG_WAREHOUSES  = 'warehouses';
    public const TAG_ROLES       = 'roles';
    public const TAG_UNITS       = 'units';
    public const TAG_DEPARTMENTS = 'departments';

    // ── 核心方法 ────────────────────────────────

    /**
     * 生成带企业前缀的缓存 key
     */
    public static function key(string $tag, string $identifier, ?int $tenantId = null): string
    {
        $tenantId = $tenantId ?? TenantContext::getTenantId();
        $prefix = $tenantId ? "t{$tenantId}" : 'global';
        return "{$prefix}:{$tag}:{$identifier}";
    }

    /**
     * 获取或缓存数据（remember 模式）
     *
     * @param string   $tag        缓存标签（如 'products'）
     * @param string   $identifier 标识符（如 'list:page1' 或 'detail:123'）
     * @param int      $ttl        过期秒数
     * @param callable $callback   缓存未命中时执行的回调
     * @param int|null $tenantId   企业 ID（默认取当前上下文）
     * @return mixed
     */
    public static function remember(string $tag, string $identifier, int $ttl, callable $callback, ?int $tenantId = null): mixed
    {
        $cacheKey = self::key($tag, $identifier, $tenantId);
        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * 直接写入缓存
     */
    public static function put(string $tag, string $identifier, mixed $value, int $ttl, ?int $tenantId = null): void
    {
        $cacheKey = self::key($tag, $identifier, $tenantId);
        Cache::put($cacheKey, $value, $ttl);
    }

    /**
     * 获取缓存（不写入）
     */
    public static function get(string $tag, string $identifier, ?int $tenantId = null): mixed
    {
        $cacheKey = self::key($tag, $identifier, $tenantId);
        return Cache::get($cacheKey);
    }

    /**
     * 删除指定缓存
     */
    public static function forget(string $tag, string $identifier, ?int $tenantId = null): void
    {
        $cacheKey = self::key($tag, $identifier, $tenantId);
        Cache::forget($cacheKey);
    }

    /**
     * 批量清除某个标签下的所有缓存（使用模式匹配）
     *
     * 注意：这依赖 Redis 的 SCAN 命令，不适用于其他缓存驱动。
     * 对于非 Redis 驱动，使用 forgetByKeys 方法代替。
     */
    public static function flushTag(string $tag, ?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? TenantContext::getTenantId();
        $prefix = $tenantId ? "t{$tenantId}" : 'global';

        // 使用 Redis 的 SCAN + DEL 批量删除
        try {
            $redisPrefix = config('cache.prefix') . ':';
            $pattern = $redisPrefix . "{$prefix}:{$tag}:*";

            /** @var \Illuminate\Cache\RedisStore $store */
            $store = Cache::getStore();
            /** @var \Redis $redis */
            $redis = $store->getRedis();
            $cursor = null;
            do {
                /** @var array{0: int, 1: array} $result */
                $result = $redis->scan($cursor ?? 0, ['match' => $pattern, 'count' => 100]);
                [$cursor, $keys] = $result;
                if (!empty($keys)) {
                    $redis->del(...$keys);
                }
            } while ($cursor != 0);
        } catch (\Throwable) {
            // 如果不是 Redis 驱动或 Redis 异常，忽略
        }
    }

    /**
     * 清除某个标签下已知的特定 key 列表
     */
    public static function forgetByKeys(string $tag, array $identifiers, ?int $tenantId = null): void
    {
        foreach ($identifiers as $id) {
            self::forget($tag, $id, $tenantId);
        }
    }
}
