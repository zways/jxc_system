<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckEnterpriseData extends Command
{
    protected $signature = 'jxc:check-enterprise-data';
    protected $description = '检查企业账号(enterprise)与 STORE0001 测试数据是否一致，用于排查「登录后没有数据」';

    public function handle(): int
    {
        $store = Store::withoutGlobalScopes()->where('store_code', 'STORE0001')->first();
        $user = User::withoutGlobalScope(TenantScope::class)->where('username', 'enterprise')->first();

        $this->line('');
        $this->info('--- 企业账号与测试数据诊断 ---');
        $this->line('');

        if (!$store) {
            $this->error('未找到门店 STORE0001。请执行: php artisan db:seed --class=StoresTableSeeder');
            return self::FAILURE;
        }
        $this->line("STORE0001: id={$store->id}, name={$store->name}");

        if (!$user) {
            $this->error('未找到用户 enterprise。请执行: php artisan db:seed --class=EnterpriseUserSeeder');
            return self::FAILURE;
        }
        $this->line("用户 enterprise: id={$user->id}, store_id=" . ($user->store_id ?? 'null'));

        if ((int) $user->store_id !== (int) $store->id) {
            $this->warn("用户 store_id({$user->store_id}) 与 STORE0001 的 id({$store->id}) 不一致，企业账号会看不到该门店的数据。");
            $this->line('建议: php artisan db:seed --class=EnterpriseUserSeeder');
        } else {
            $this->info('用户归属门店与 STORE0001 一致。');
        }

        $storeId = $store->id;
        $counts = [
            'suppliers' => DB::table('suppliers')->where('store_id', $storeId)->count(),
            'products' => DB::table('products')->where('store_id', $storeId)->count(),
            'purchase_orders' => DB::table('purchase_orders')->where('store_id', $storeId)->count(),
            'sales_orders' => DB::table('sales_orders')->where('store_id', $storeId)->count(),
            'warehouses' => DB::table('warehouses')->where('store_id', $storeId)->count(),
        ];
        $this->line('');
        $this->info('STORE0001 下当前数据条数:');
        foreach ($counts as $table => $count) {
            $this->line("  {$table}: {$count}");
        }
        $total = array_sum($counts);
        if ($total === 0) {
            $this->line('');
            $this->warn('STORE0001 下无业务数据。请执行: php artisan db:seed --class=AssignTestDataToEnterpriseStoreSeeder');
            $this->line('或完整初始化: php artisan db:seed');
        } else {
            $this->line('');
            $this->info("合计 {$total} 条，企业账号 enterprise 登录后应可见。若仍无数据，请检查前端请求是否带正确 Token。");
        }
        $this->line('');

        return self::SUCCESS;
    }
}
