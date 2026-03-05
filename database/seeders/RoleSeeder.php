<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => '超级管理员', 'code' => 'super_admin', 'description' => '系统最高权限', 'is_active' => true],
            ['name' => '部门经理', 'code' => 'manager', 'description' => '部门管理权限', 'is_active' => true],
            ['name' => '普通员工', 'code' => 'staff', 'description' => '基础操作权限', 'is_active' => true],
        ];

        foreach ($roles as $r) {
            Role::updateOrCreate(['code' => $r['code']], $r);
        }
    }
}

