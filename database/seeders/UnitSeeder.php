<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => '个', 'symbol' => '个', 'remark' => '基本单位', 'is_active' => true],
            ['name' => '台', 'symbol' => '台', 'remark' => '设备类', 'is_active' => true],
            ['name' => '件', 'symbol' => '件', 'remark' => '服装等', 'is_active' => true],
            ['name' => '箱', 'symbol' => '箱', 'remark' => '整箱', 'is_active' => true],
            ['name' => '千克', 'symbol' => 'kg', 'remark' => '重量', 'is_active' => true],
        ];

        foreach ($items as $u) {
            Unit::updateOrCreate(['name' => $u['name']], $u);
        }
    }
}

