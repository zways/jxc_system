<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;

class PurchaseOrderItemsSeeder extends Seeder
{
    public function run(): void
    {
        $po1 = PurchaseOrder::where('order_number', 'PO202602001')->first();
        $po2 = PurchaseOrder::where('order_number', 'PO202602002')->first();
        $po3 = PurchaseOrder::where('order_number', 'PO202602003')->first();

        $p1 = Product::where('code', 'PROD001')->first();
        $p3 = Product::where('code', 'PROD003')->first();
        $p4 = Product::where('code', 'PROD004')->first();

        if ($po1 && $p1) {
            PurchaseOrderItem::firstOrCreate(
                ['purchase_order_id' => $po1->id, 'product_id' => $p1->id],
                [
                    'product_name' => $p1->name,
                    'unit' => $p1->unit,
                    'quantity' => 20,
                    'unit_price' => 2800,
                    'line_amount' => 56000,
                    'notes' => 'PO202602001 明细',
                ]
            );
        }

        if ($po2 && $p3) {
            PurchaseOrderItem::firstOrCreate(
                ['purchase_order_id' => $po2->id, 'product_id' => $p3->id],
                [
                    'product_name' => $p3->name,
                    'unit' => $p3->unit,
                    'quantity' => 50,
                    'unit_price' => 80,
                    'line_amount' => 4000,
                    'notes' => 'PO202602002 明细',
                ]
            );
        }

        if ($po3 && $p4) {
            PurchaseOrderItem::firstOrCreate(
                ['purchase_order_id' => $po3->id, 'product_id' => $p4->id],
                [
                    'product_name' => $p4->name,
                    'unit' => $p4->unit,
                    'quantity' => 12,
                    'unit_price' => 15,
                    'line_amount' => 180,
                    'notes' => 'PO202602003 明细',
                ]
            );
        }
    }
}
