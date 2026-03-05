<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Supplier;

class SuppliersTableSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'supplier_code' => 'SUP001',
                'name' => '华强北电子有限公司',
                'contact_person' => '张经理',
                'phone' => '13800138000',
                'email' => 'zhang@hqbei.com',
                'address' => '深圳市福田区华强北路',
                'tax_number' => '91440300123456789X',
                'credit_limit' => 50000.00,
                'payment_terms' => '月结30天',
                'rating' => 4,
                'notes' => '主要供应电子产品',
                'is_active' => true,
            ],
            [
                'supplier_code' => 'SUP002',
                'name' => '纺织品进出口公司',
                'contact_person' => '李总',
                'phone' => '13900139000',
                'email' => 'li@textile.com',
                'address' => '上海市浦东新区',
                'tax_number' => '91310115123456789Y',
                'credit_limit' => 80000.00,
                'payment_terms' => '货到付款',
                'rating' => 5,
                'notes' => '服装面料供应商',
                'is_active' => true,
            ],
            [
                'supplier_code' => 'SUP003',
                'name' => '食品加工厂',
                'contact_person' => '王厂长',
                'phone' => '13700137000',
                'email' => 'wang@food.com',
                'address' => '广州市天河区',
                'tax_number' => '91440106123456789Z',
                'credit_limit' => 30000.00,
                'payment_terms' => '预付50%',
                'rating' => 3,
                'notes' => '提供各类食品',
                'is_active' => true,
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::updateOrCreate(['supplier_code' => $supplier['supplier_code']], $supplier);
        }
    }
}
