<?php

namespace App\Observers;

use App\Models\Role;
use App\Services\PermissionCacheService;

/**
 * 权限缓存失效观察者 — 注册到 Role 模型。
 *
 * 当角色属性（is_active、code 等）变更或删除时，
 * 清除该角色下所有用户的权限缓存。
 *
 * 注：权限同步（pivot 表操作）不会触发 Role 的 saved 事件，
 *     已在 RolesController::syncPermissions() 中手动调用 flushRole()。
 */
class PermissionCacheObserver
{
    public function saved(Role $role): void
    {
        PermissionCacheService::flushRole($role->id);
    }

    public function deleted(Role $role): void
    {
        PermissionCacheService::flushRole($role->id);
    }

    public function restored(Role $role): void
    {
        PermissionCacheService::flushRole($role->id);
    }
}
