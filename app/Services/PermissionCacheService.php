<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * 权限缓存服务 — 将用户的角色 + 权限列表缓存到 Redis。
 *
 * 核心思路：
 * - 每个用户独立缓存一份 { role_code, role_is_active, permissions[] }
 * - 缓存 key 格式：user:{userId}:permissions
 * - TTL 2 小时，角色/权限变更时主动失效
 *
 * 失效时机：
 * 1. 角色的权限列表变更 → flushRole($roleId) → 清除该角色下所有用户缓存
 * 2. 用户角色变更 → forgetUser($userId)
 * 3. 角色停用/删除 → flushRole($roleId)
 */
class PermissionCacheService
{
    /** 缓存 TTL：2 小时 */
    private const TTL = 7200;

    /** 缓存 key 前缀 */
    private const PREFIX = 'user_perm';

    /**
     * 获取用户的权限数据（优先从缓存读取）
     *
     * @return array{role_code: string|null, role_is_active: bool, permissions: string[]}
     */
    public static function getPermissions(User $user): array
    {
        $cacheKey = self::userKey($user->id);

        return Cache::remember($cacheKey, self::TTL, function () use ($user) {
            return self::buildPermissionData($user);
        });
    }

    /**
     * 检查用户是否拥有指定权限（使用缓存）
     */
    public static function hasPermission(User $user, string $permissionName): bool
    {
        $data = self::getPermissions($user);

        // 角色未激活
        if (!$data['role_is_active']) {
            return false;
        }

        // 超级管理员直接放行
        if ($data['role_code'] === 'super_admin') {
            return true;
        }

        return in_array($permissionName, $data['permissions'], true);
    }

    /**
     * 清除单个用户的权限缓存
     */
    public static function forgetUser(int $userId): void
    {
        Cache::forget(self::userKey($userId));
    }

    /**
     * 清除某个角色下所有用户的权限缓存
     *
     * 当角色权限变更、角色停用/删除时调用。
     */
    public static function flushRole(int $roleId): void
    {
        // 查询该角色下的所有用户 ID
        $userIds = \App\Models\User::withoutGlobalScopes()
            ->where('role_id', $roleId)
            ->pluck('id');

        foreach ($userIds as $userId) {
            self::forgetUser($userId);
        }
    }

    /**
     * 预热用户权限缓存（在登录时调用，提前加载到 Redis）
     */
    public static function warmup(User $user): void
    {
        $cacheKey = self::userKey($user->id);
        $data = self::buildPermissionData($user);
        Cache::put($cacheKey, $data, self::TTL);
    }

    // ── 私有方法 ────────────────────────────────

    private static function userKey(int $userId): string
    {
        return self::PREFIX . ":{$userId}";
    }

    /**
     * 从数据库构建权限数据结构
     */
    private static function buildPermissionData(User $user): array
    {
        $role = $user->role;

        if (!$role) {
            return [
                'role_code' => null,
                'role_is_active' => false,
                'permissions' => [],
            ];
        }

        $permissions = $role->permissions()
            ->pluck('name')
            ->toArray();

        return [
            'role_code' => $role->code ?? null,
            'role_is_active' => (bool) $role->is_active,
            'permissions' => $permissions,
        ];
    }
}
