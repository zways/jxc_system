<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => '技术部', 'code' => 'tech', 'description' => '技术研发', 'is_active' => true],
            ['name' => '销售部', 'code' => 'sales', 'description' => '销售业务', 'is_active' => true],
            ['name' => '采购部', 'code' => 'purchase', 'description' => '采购业务', 'is_active' => true],
            ['name' => '财务部', 'code' => 'finance', 'description' => '财务管理', 'is_active' => true],
            ['name' => '仓储部', 'code' => 'warehouse', 'description' => '仓储管理', 'is_active' => true],
        ];

        foreach ($items as $d) {
            Department::updateOrCreate(['code' => $d['code']], $d);
        }
    }
}

