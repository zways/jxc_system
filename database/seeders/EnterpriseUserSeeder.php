<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 企业账户（企业用户）用于 E2E 测试与日常演示。
 * 与超管区别：归属门店 store_id，无「企业管理」菜单，有「企业信息」。
 */
class EnterpriseUserSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::where('store_code', 'STORE0001')->first();
        if (!$store) {
            return;
        }

        $allPermissionIds = Permission::where('is_active', true)->pluck('id')->all();

        $role = Role::updateOrCreate(
            ['store_id' => $store->id, 'code' => 'tenant_admin'],
            [
                'name' => '企业管理员',
                'description' => '企业管理员，拥有本企业全部权限',
                'is_active' => true,
            ]
        );
        $role->permissions()->sync($allPermissionIds);

        $dept = Department::where('store_id', $store->id)->first();
        if (!$dept) {
            $dept = Department::create([
                'store_id' => $store->id,
                'name' => '管理部',
                'code' => 'admin',
                'description' => '默认管理部门',
                'is_active' => true,
            ]);
        }

        $warehouse = Warehouse::where('store_id', $store->id)->first();

        User::updateOrCreate(
            ['email' => 'enterprise@example.com'],
            [
                'name' => 'Enterprise User',
                'username' => 'enterprise',
                'real_name' => '企业管理员',
                'phone' => '13800138002',
                'password' => Hash::make('Enterprise@2026'),
                'email_verified_at' => now(),
                'status' => 'enabled',
                'employee_code' => 'E0001',
                'role_id' => $role->id,
                'department_id' => $dept->id,
                'store_id' => $store->id,
                'warehouse_id' => $warehouse?->id,
            ]
        );
    }
}
