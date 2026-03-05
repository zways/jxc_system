<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Department;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roleId = Role::where('code', 'super_admin')->value('id');
        $deptId = Department::where('code', 'tech')->value('id');

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'username' => 'admin',
                'real_name' => '管理员',
                'phone' => '13800138001',
                'password' => Hash::make('Admin@2026'),
                'email_verified_at' => now(),
                'status' => 'enabled',
                'employee_code' => 'A0001',
                'role_id' => $roleId,
                'department_id' => $deptId,
            ]
        );
    }
}
