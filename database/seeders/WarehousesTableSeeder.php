<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Warehouse;

class WarehousesTableSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouses = [
            [
                'code' => 'WH001',
                'name' => '总部仓库',
                'location' => '深圳市南山区科技园',
                'manager' => '王仓库',
                'description' => '公司总部主要仓库',
                'type' => 'normal',
                'is_active' => true,
                'notes' => '主要存放电子产品',
            ],
            [
                'code' => 'WH002',
                'name' => '上海分仓',
                'location' => '上海市浦东新区',
                'manager' => '李仓库',
                'description' => '华东地区分仓库',
                'type' => 'normal',
                'is_active' => true,
                'notes' => '主要存放日用品',
            ],
            [
                'code' => 'WH003',
                'name' => '冷链仓库',
                'location' => '广州市天河区',
                'manager' => '张仓库',
                'description' => '专门存放冷冻食品',
                'type' => 'frozen',
                'is_active' => true,
                'notes' => '温度控制在-18°C',
            ],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::updateOrCreate(['code' => $warehouse['code']], $warehouse);
        }
    }
}
