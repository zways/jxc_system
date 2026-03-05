<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ProductCategory;

class ProductCategoriesTableSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => '电子产品', 'description' => '各类电子设备及配件'],
            ['name' => '服装鞋帽', 'description' => '男女装、童装及鞋帽'],
            ['name' => '家居用品', 'description' => '家具、家纺、厨具等'],
            ['name' => '食品饮料', 'description' => '各类食品及饮品'],
            ['name' => '办公用品', 'description' => '办公设备及文具'],
            ['name' => '运动户外', 'description' => '运动器材及户外用品'],
            ['name' => '美妆个护', 'description' => '化妆品及个人护理用品'],
            ['name' => '母婴用品', 'description' => '婴儿及孕妇用品'],
        ];

        foreach ($categories as $category) {
            ProductCategory::updateOrCreate(
                ['name' => $category['name']],
                [
                    'description' => $category['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
