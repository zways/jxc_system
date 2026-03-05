<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Product;

class SalesOrderItemsSeeder extends Seeder
{
    public function run(): void
    {
        $so1 = SalesOrder::where('order_number', 'SO202602001')->first();
        $so2 = SalesOrder::where('order_number', 'SO202602002')->first();
        $so3 = SalesOrder::where('order_number', 'SO202602003')->first();

        $p1 = Product::where('code', 'PROD001')->first();
        $p3 = Product::where('code', 'PROD003')->first();

        if ($so1 && $p1) {
            SalesOrderItem::firstOrCreate(
                ['sales_order_id' => $so1->id, 'product_id' => $p1->id],
                [
                    'product_name' => $p1->name,
                    'unit' => $p1->unit,
                    'quantity' => 5,
                    'unit_price' => 3200,
                    'line_amount' => 16000,
                    'notes' => 'SO202602001 明细',
                ]
            );
        }

        if ($so2 && $p3) {
            SalesOrderItem::firstOrCreate(
                ['sales_order_id' => $so2->id, 'product_id' => $p3->id],
                [
                    'product_name' => $p3->name,
                    'unit' => $p3->unit,
                    'quantity' => 24,
                    'unit_price' => 120,
                    'line_amount' => 2880,
                    'notes' => 'SO202602002 明细',
                ]
            );
        }

        if ($so3 && $p1) {
            SalesOrderItem::firstOrCreate(
                ['sales_order_id' => $so3->id, 'product_id' => $p1->id],
                [
                    'product_name' => $p1->name,
                    'unit' => $p1->unit,
                    'quantity' => 2,
                    'unit_price' => 3500,
                    'line_amount' => 7000,
                    'notes' => 'SO202602003 明细',
                ]
            );
        }
    }
}
