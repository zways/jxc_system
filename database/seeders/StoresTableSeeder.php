<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;

class StoresTableSeeder extends Seeder
{
    public function run(): void
    {
        $headOffice = Store::updateOrCreate(
            ['store_code' => 'STORE0001'],
            [
                'name' => '总部直营店',
                'manager' => '王经理',
                'phone' => '021-60000001',
                'address' => '上海市浦东新区世纪大道100号',
                'type' => 'retail',
                'is_active' => true,
                'notes' => '总部直营门店',
            ]
        );

        Store::updateOrCreate(
            ['store_code' => 'STORE0002'],
            [
                'name' => '徐汇分店',
                'manager' => '李店长',
                'phone' => '021-60000002',
                'address' => '上海市徐汇区漕溪北路200号',
                'type' => 'retail',
                'is_active' => true,
                'parent_store_id' => $headOffice->id,
                'notes' => '分店',
            ]
        );

        Store::updateOrCreate(
            ['store_code' => 'STORE0003'],
            [
                'name' => '线上旗舰店',
                'manager' => '陈主管',
                'phone' => '400-800-1234',
                'address' => '线上渠道',
                'type' => 'online',
                'is_active' => true,
                'notes' => '电商渠道门店',
            ]
        );
    }
}
