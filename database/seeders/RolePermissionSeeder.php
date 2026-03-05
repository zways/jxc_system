<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissionIds = Permission::query()->pluck('id')->all();

        $superAdmin = Role::query()->where('code', 'super_admin')->first();
        if ($superAdmin) {
            $superAdmin->permissions()->sync($allPermissionIds);
        }

        // 默认给 manager/staff 一些“只读”权限，避免新装系统进来全是 403
        $readPermissionIds = Permission::query()
            ->where('name', 'like', '%.read')
            ->pluck('id')
            ->all();

        $manager = Role::query()->where('code', 'manager')->first();
        if ($manager) {
            $manager->permissions()->sync($readPermissionIds);
        }

        $staff = Role::query()->where('code', 'staff')->first();
        if ($staff) {
            $staff->permissions()->sync($readPermissionIds);
        }
    }
}

