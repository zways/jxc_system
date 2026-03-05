<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $resources = [
            ['name' => 'products', 'title' => '商品'],
            ['name' => 'product-categories', 'title' => '商品分类'],
            ['name' => 'suppliers', 'title' => '供应商'],
            ['name' => 'customers', 'title' => '客户'],
            ['name' => 'purchase-orders', 'title' => '采购订单'],
            ['name' => 'sales-orders', 'title' => '销售订单'],
            ['name' => 'warehouses', 'title' => '仓库'],
            ['name' => 'accounts-payable', 'title' => '应付账款'],
            ['name' => 'accounts-receivable', 'title' => '应收账款'],
            ['name' => 'financial-transactions', 'title' => '财务流水'],
            ['name' => 'inventory-adjustments', 'title' => '库存调整'],
            ['name' => 'inventory-transactions', 'title' => '库存流水'],
            ['name' => 'exchange-records', 'title' => '换货记录'],
            ['name' => 'inventory-counts', 'title' => '库存盘点'],
            ['name' => 'sales-returns', 'title' => '销售退货'],
            ['name' => 'stores', 'title' => '门店'],
            ['name' => 'business-agents', 'title' => '业务员'],
            ['name' => 'roles', 'title' => '角色'],
            ['name' => 'departments', 'title' => '部门'],
            ['name' => 'units', 'title' => '单位'],
            ['name' => 'users', 'title' => '用户'],
            ['name' => 'permissions', 'title' => '权限'],
            ['name' => 'reports', 'title' => '报表'],
            ['name' => 'test-system', 'title' => '系统测试'],
            ['name' => 'dashboard', 'title' => '仪表盘'],
        ];

        $actions = [
            ['key' => 'read', 'title' => '查看'],
            ['key' => 'create', 'title' => '新增'],
            ['key' => 'update', 'title' => '修改'],
            ['key' => 'delete', 'title' => '删除'],
        ];

        foreach ($resources as $res) {
            foreach ($actions as $act) {
                $name = "{$res['name']}.{$act['key']}";
                Permission::updateOrCreate(
                    ['name' => $name],
                    [
                        'title' => "{$res['title']}-{$act['title']}",
                        'group' => $this->guessGroup($res['name']),
                        'description' => null,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function guessGroup(string $resource): string
    {
        return match (true) {
            str_starts_with($resource, 'purchase') || $resource === 'suppliers' => 'purchase',
            str_starts_with($resource, 'sales') || $resource === 'customers' || $resource === 'exchange-records' => 'sales',
            str_starts_with($resource, 'inventory') || $resource === 'warehouses' => 'inventory',
            str_starts_with($resource, 'accounts') || $resource === 'financial-transactions' => 'finance',
            in_array($resource, ['users', 'roles', 'departments', 'units', 'permissions']) => 'system',
            $resource === 'reports' => 'reports',
            default => 'other',
        };
    }
}

