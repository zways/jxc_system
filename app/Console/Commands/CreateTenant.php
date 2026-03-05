<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateTenant extends Command
{
    protected $signature = 'tenant:create
        {--name= : 企业名称}
        {--admin-name= : 管理员姓名}
        {--admin-email= : 管理员邮箱}
        {--admin-password= : 管理员密码}
        {--plan=free : 套餐(free/basic/pro/enterprise)}
        {--industry= : 所属行业}';

    protected $description = '创建一个新的企业（企业）及其管理员账号';

    public function handle(): int
    {
        $companyName = $this->option('name') ?: $this->ask('企业名称');
        $adminName = $this->option('admin-name') ?: $this->ask('管理员姓名');
        $adminEmail = $this->option('admin-email') ?: $this->ask('管理员邮箱');
        $adminPassword = $this->option('admin-password') ?: $this->secret('管理员密码');
        $plan = $this->option('plan');
        $industry = $this->option('industry');

        if (!$companyName || !$adminName || !$adminEmail || !$adminPassword) {
            $this->error('企业名称、管理员姓名、邮箱和密码为必填项');
            return self::FAILURE;
        }

        // 检查邮箱唯一性
        if (User::where('email', $adminEmail)->exists()) {
            $this->error("邮箱 {$adminEmail} 已被使用");
            return self::FAILURE;
        }

        $plans = Store::availablePlans();
        $maxUsers = $plans[$plan]['max_users'] ?? 5;

        $this->info("正在创建企业...");
        $this->table(['属性', '值'], [
            ['企业名称', $companyName],
            ['管理员', $adminName],
            ['邮箱', $adminEmail],
            ['套餐', $plans[$plan]['name'] ?? $plan],
            ['最大用户数', $maxUsers],
        ]);

        if (!$this->confirm('确认创建？')) {
            return self::SUCCESS;
        }

        try {
            $result = DB::transaction(function () use ($companyName, $adminName, $adminEmail, $adminPassword, $plan, $maxUsers, $industry) {
                // 1. 创建门店/企业
                $storeCode = 'T' . date('Ymd') . Str::upper(Str::random(4));
                $store = Store::create([
                    'store_code' => $storeCode,
                    'name' => $companyName,
                    'manager' => $adminName,
                    'contact_email' => $adminEmail,
                    'type' => 'retail',
                    'industry' => $industry,
                    'is_active' => true,
                    'plan' => $plan,
                    'max_users' => $maxUsers,
                    'expires_at' => null,
                    'is_tenant' => true,
                ]);

                // 2. 创建管理员角色
                $role = Role::create([
                    'store_id' => $store->id,
                    'name' => '管理员',
                    'code' => 'tenant_admin',
                    'description' => '企业管理员，拥有本企业全部权限',
                    'is_active' => true,
                ]);
                $allPermissions = Permission::where('is_active', true)->pluck('id');
                $role->permissions()->sync($allPermissions);

                // 3. 创建默认部门
                $dept = Department::create([
                    'store_id' => $store->id,
                    'name' => '管理部',
                    'code' => 'admin',
                    'description' => '默认管理部门',
                    'is_active' => true,
                ]);

                // 4. 创建默认仓库
                $warehouse = Warehouse::create([
                    'store_id' => $store->id,
                    'code' => 'WH001',
                    'name' => '默认仓库',
                    'manager' => $adminName,
                    'type' => 'normal',
                    'is_active' => true,
                ]);

                // 5. 创建管理员用户
                $username = 'admin_' . strtolower(Str::random(6));
                $user = User::create([
                    'username' => $username,
                    'real_name' => $adminName,
                    'name' => $adminName,
                    'email' => $adminEmail,
                    'password' => Hash::make($adminPassword),
                    'status' => 'enabled',
                    'role_id' => $role->id,
                    'department_id' => $dept->id,
                    'store_id' => $store->id,
                    'warehouse_id' => $warehouse->id,
                ]);

                return compact('store', 'user', 'username');
            });

            $this->newLine();
            $this->info('✓ 企业创建成功！');
            $this->table(['属性', '值'], [
                ['企业 ID', $result['store']->id],
                ['门店编码', $result['store']->store_code],
                ['用户名', $result['username']],
                ['管理员邮箱', $adminEmail],
            ]);
            $this->warn('请牢记用户名和密码，使用它们登录系统。');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('创建失败: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
