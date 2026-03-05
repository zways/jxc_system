<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductCategory;

class ProductsTableSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 获取分类ID
        $electronicsCategory = ProductCategory::where('name', '电子产品')->first();
        $clothingCategory = ProductCategory::where('name', '服装鞋帽')->first();
        $homeCategory = ProductCategory::where('name', '家居用品')->first();

        $products = [
            [
                'code' => 'PROD001',
                'name' => '智能手机',
                'description' => '最新款智能手机，功能强大',
                'category_id' => $electronicsCategory ? $electronicsCategory->id : 1,
                'barcode' => '6901234567890',
                'specification' => '6.5寸屏, 128GB',
                'unit' => '台',
                'second_unit' => '箱',
                'conversion_rate' => 20.00,
                'purchase_price' => 2800.00,
                'retail_price' => 3500.00,
                'wholesale_price' => 3200.00,
                'min_stock' => 10.00,
                'max_stock' => 200.00,
                'track_serial' => true,
                'track_batch' => false,
                'is_active' => true,
            ],
            [
                'code' => 'PROD002',
                'name' => '笔记本电脑',
                'description' => '高性能商务笔记本',
                'category_id' => $electronicsCategory ? $electronicsCategory->id : 1,
                'barcode' => '6901234567891',
                'specification' => 'i7处理器, 16GB内存',
                'unit' => '台',
                'second_unit' => '箱',
                'conversion_rate' => 10.00,
                'purchase_price' => 5500.00,
                'retail_price' => 6800.00,
                'wholesale_price' => 6200.00,
                'min_stock' => 5.00,
                'max_stock' => 50.00,
                'track_serial' => true,
                'track_batch' => false,
                'is_active' => true,
            ],
            [
                'code' => 'PROD003',
                'name' => '男式休闲衬衫',
                'description' => '纯棉舒适男式衬衫',
                'category_id' => $clothingCategory ? $clothingCategory->id : 2,
                'barcode' => '6901234567892',
                'specification' => 'L码，纯棉',
                'unit' => '件',
                'second_unit' => '打',
                'conversion_rate' => 12.00,
                'purchase_price' => 80.00,
                'retail_price' => 150.00,
                'wholesale_price' => 120.00,
                'min_stock' => 20.00,
                'max_stock' => 300.00,
                'track_serial' => false,
                'track_batch' => false,
                'is_active' => true,
            ],
            [
                'code' => 'PROD004',
                'name' => '不锈钢保温杯',
                'description' => '500ml不锈钢保温杯',
                'category_id' => $homeCategory ? $homeCategory->id : 3,
                'barcode' => '6901234567893',
                'specification' => '500ml，不锈钢',
                'unit' => '个',
                'second_unit' => '盒',
                'conversion_rate' => 24.00,
                'purchase_price' => 15.00,
                'retail_price' => 35.00,
                'wholesale_price' => 25.00,
                'min_stock' => 50.00,
                'max_stock' => 500.00,
                'track_serial' => false,
                'track_batch' => true,
                'is_active' => true,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(['code' => $product['code']], $product);
        }
    }
}
