<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 将当前 store_id 为空的测试数据归属到 STORE0001（企业账号 enterprise 所属门店），
 * 使企业账号登录后能看到供应商、商品、采购单等测试数据。
 *
 * 执行顺序：须在 StoresTableSeeder 及所有业务数据 Seeder 之后、EnterpriseUserSeeder 之前。
 * 若 STORE0001 不存在会先执行 StoresTableSeeder 创建门店。
 */
class AssignTestDataToEnterpriseStoreSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::withoutGlobalScopes()->where('store_code', 'STORE0001')->first();
        if (!$store) {
            $this->call(StoresTableSeeder::class);
            $store = Store::withoutGlobalScopes()->where('store_code', 'STORE0001')->first();
        }
        if (!$store) {
            $this->command?->warn('未找到 STORE0001，跳过归属测试数据。请先执行: php artisan db:seed --class=StoresTableSeeder');
            return;
        }

        $storeId = $store->id;
        // 有 (store_id, 某编码) 唯一约束的表：只归属「编码在目标门店下尚不存在」的 null 行，避免重复键
        $tablesWithUniqueCode = [
            'suppliers' => 'supplier_code',
            'customers' => 'customer_code',
            'business_agents' => 'agent_code',
            'product_categories' => 'name',
            'units' => 'name',
            'warehouses' => 'code',
            'departments' => 'code',
            'products' => 'code',
        ];
        $tablesSimple = [
            'purchase_orders',
            'sales_orders',
            'sales_returns',
            'exchange_records',
            'inventory_transactions',
            'inventory_adjustments',
            'inventory_counts',
            'accounts_payable',
            'accounts_receivable',
            'financial_transactions',
        ];

        $total = 0;
        foreach ($tablesWithUniqueCode as $table => $codeColumn) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'store_id') || !Schema::hasColumn($table, $codeColumn)) {
                continue;
            }
            $existing = DB::table($table)->where('store_id', $storeId)->pluck($codeColumn)->all();
            // 每个编码只归属一条（同编码多条 null 时只更新一条，避免唯一键冲突）
            $idsToUpdate = DB::table($table)
                ->whereNull('store_id')
                ->whereNotIn($codeColumn, $existing)
                ->orderBy('id')
                ->get()
                ->unique($codeColumn)
                ->pluck('id');
            if ($idsToUpdate->isNotEmpty()) {
                $updated = DB::table($table)->whereIn('id', $idsToUpdate)->update(['store_id' => $storeId]);
                $total += $updated;
                if ($this->command && $updated > 0) {
                    $this->command->info("  {$table}: {$updated} 条归属到 STORE0001 (id={$storeId})");
                }
            }
        }
        foreach ($tablesSimple as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'store_id')) {
                continue;
            }
            $updated = DB::table($table)->whereNull('store_id')->update(['store_id' => $storeId]);
            $total += $updated;
            if ($this->command && $updated > 0) {
                $this->command->info("  {$table}: {$updated} 条归属到 STORE0001 (id={$storeId})");
            }
        }
        if ($this->command) {
            $this->command->info("AssignTestDataToEnterpriseStore: 共 {$total} 条数据已归属到 STORE0001，企业账号 enterprise 登录后应可见。");
        }
    }
}
