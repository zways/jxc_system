<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\User;

class PurchaseOrdersSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $userId = User::first()?->id ?? 1;
        $wh1 = Warehouse::where('code', 'WH001')->first()?->id ?? 1;
        $wh2 = Warehouse::where('code', 'WH002')->first()?->id ?? 2;
        $sup1 = Supplier::where('supplier_code', 'SUP001')->first()?->id ?? 1;
        $sup2 = Supplier::where('supplier_code', 'SUP002')->first()?->id ?? 2;

        $orders = [
            ['order_number' => 'PO202602001', 'supplier_id' => $sup1, 'order_date' => now()->subDays(5), 'expected_delivery_date' => now()->subDays(1), 'subtotal' => 33600, 'discount' => 0, 'tax_amount' => 0, 'shipping_cost' => 200, 'total_amount' => 33800, 'status' => 'received', 'payment_status' => 'paid', 'delivery_status' => 'delivered', 'warehouse_id' => $wh1, 'notes' => '智能手机采购'],
            ['order_number' => 'PO202602002', 'supplier_id' => $sup2, 'order_date' => now()->subDays(4), 'expected_delivery_date' => now(), 'subtotal' => 9600, 'discount' => 200, 'tax_amount' => 0, 'shipping_cost' => 0, 'total_amount' => 9400, 'status' => 'received', 'payment_status' => 'partial', 'delivery_status' => 'delivered', 'warehouse_id' => $wh2, 'notes' => '衬衫补货'],
            ['order_number' => 'PO202602003', 'supplier_id' => Supplier::where('supplier_code', 'SUP003')->first()?->id ?? 3, 'order_date' => now()->subDays(3), 'expected_delivery_date' => now()->addDays(2), 'subtotal' => 360, 'discount' => 0, 'tax_amount' => 0, 'shipping_cost' => 50, 'total_amount' => 410, 'status' => 'confirmed', 'payment_status' => 'unpaid', 'delivery_status' => 'pending', 'warehouse_id' => $wh1, 'notes' => '保温杯采购'],
        ];

        foreach ($orders as $o) {
            PurchaseOrder::firstOrCreate(
                ['order_number' => $o['order_number']],
                array_merge($o, ['created_by' => $userId])
            );
        }
    }
}
