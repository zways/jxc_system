<?php

namespace App\Services;

/**
 * 企业上下文（多企业核心服务）
 *
 * 在请求生命周期中保存当前企业信息。
 * - API 请求：由 AuthenticateWithApiToken 中间件在用户认证后激活
 * - CLI/队列任务：默认不激活，全局作用域不会过滤数据
 * - 超级管理员：激活但跳过过滤，可以看到所有企业数据
 */
class TenantContext
{
    /** 当前企业 ID（即 store_id） */
    private static ?int $tenantId = null;

    /** 是否为超级管理员 */
    private static bool $isSuperAdmin = false;

    /** 企业上下文是否已激活（区分 API 请求 vs CLI 环境） */
    private static bool $isActive = false;

    /**
     * 激活企业上下文（在认证中间件中调用）
     */
    public static function activate(?int $tenantId, bool $isSuperAdmin = false): void
    {
        self::$isActive = true;
        self::$tenantId = $tenantId;
        self::$isSuperAdmin = $isSuperAdmin;
    }

    /**
     * 是否已激活企业上下文
     */
    public static function isActive(): bool
    {
        return self::$isActive;
    }

    /**
     * 获取当前企业 ID
     */
    public static function getTenantId(): ?int
    {
        return self::$tenantId;
    }

    /**
     * 设置当前企业 ID（用于超级管理员切换企业等场景）
     */
    public static function setTenantId(?int $tenantId): void
    {
        self::$tenantId = $tenantId;
    }

    /**
     * 是否为超级管理员
     */
    public static function isSuperAdmin(): bool
    {
        return self::$isSuperAdmin;
    }

    /**
     * 重置上下文（主要用于测试）
     */
    public static function reset(): void
    {
        self::$tenantId = null;
        self::$isSuperAdmin = false;
        self::$isActive = false;
    }

    /**
     * 在指定企业上下文中执行回调（临时切换企业）
     *
     * @param int|null $tenantId 临时企业 ID
     * @param callable $callback 回调函数
     * @return mixed 回调返回值
     */
    public static function runAs(?int $tenantId, callable $callback): mixed
    {
        $previousTenantId = self::$tenantId;
        $previousIsSuperAdmin = self::$isSuperAdmin;
        $previousIsActive = self::$isActive;

        self::$tenantId = $tenantId;
        self::$isSuperAdmin = false;
        self::$isActive = true;

        try {
            return $callback();
        } finally {
            self::$tenantId = $previousTenantId;
            self::$isSuperAdmin = $previousIsSuperAdmin;
            self::$isActive = $previousIsActive;
        }
    }

    /**
     * 在无企业限制的上下文中执行回调（临时禁用企业过滤）
     */
    public static function runWithoutTenant(callable $callback): mixed
    {
        $previousTenantId = self::$tenantId;
        $previousIsSuperAdmin = self::$isSuperAdmin;
        $previousIsActive = self::$isActive;

        self::$isActive = false;

        try {
            return $callback();
        } finally {
            self::$tenantId = $previousTenantId;
            self::$isSuperAdmin = $previousIsSuperAdmin;
            self::$isActive = $previousIsActive;
        }
    }
}
