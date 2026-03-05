<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;

class InventoryTransactionsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $userId = User::first()?->id ?? 1;
        $wh1 = Warehouse::where('code', 'WH001')->first()?->id ?? 1;
        $wh2 = Warehouse::where('code', 'WH002')->first()?->id ?? 2;
        $p1 = Product::where('code', 'PROD001')->first()?->id ?? 1;
        $p2 = Product::where('code', 'PROD002')->first()?->id ?? 2;
        $p3 = Product::where('code', 'PROD003')->first()?->id ?? 3;
        $p4 = Product::where('code', 'PROD004')->first()?->id ?? 4;

        $po1 = PurchaseOrder::where('order_number', 'PO202602001')->first()?->id ?? 1;
        $po2 = PurchaseOrder::where('order_number', 'PO202602002')->first()?->id ?? 2;
        $po3 = PurchaseOrder::where('order_number', 'PO202602003')->first()?->id ?? 3;
        $so1 = SalesOrder::where('order_number', 'SO202602001')->first()?->id ?? 1;
        $so2 = SalesOrder::where('order_number', 'SO202602002')->first()?->id ?? 2;
        $so3 = SalesOrder::where('order_number', 'SO202602003')->first()?->id ?? 3;

        $records = [
            // 入库（采购）
            ['transaction_number' => 'IT202602001', 'product_id' => $p1, 'warehouse_id' => $wh1, 'transaction_type' => 'in', 'quantity' => 20, 'unit' => '台', 'unit_cost' => 2800, 'total_cost' => 56000, 'reference_type' => 'purchase', 'reference_id' => $po1, 'reason' => '采购入库', 'notes' => 'PO202602001'],
            ['transaction_number' => 'IT202602002', 'product_id' => $p3, 'warehouse_id' => $wh2, 'transaction_type' => 'in', 'quantity' => 50, 'unit' => '件', 'unit_cost' => 80, 'total_cost' => 4000, 'reference_type' => 'purchase', 'reference_id' => $po2, 'reason' => '采购入库', 'notes' => 'PO202602002'],
            ['transaction_number' => 'IT202602005', 'product_id' => $p4, 'warehouse_id' => $wh1, 'transaction_type' => 'in', 'quantity' => 12, 'unit' => '个', 'unit_cost' => 15, 'total_cost' => 180, 'reference_type' => 'purchase', 'reference_id' => $po3, 'reason' => '采购入库', 'notes' => 'PO202602003'],
            // 调拨入库
            ['transaction_number' => 'IT202602009', 'product_id' => $p1, 'warehouse_id' => $wh2, 'transaction_type' => 'in', 'quantity' => 5, 'unit' => '台', 'unit_cost' => 2800, 'total_cost' => 14000, 'reference_type' => 'transfer', 'reference_id' => 1, 'reason' => '调拨入库', 'notes' => '从总部仓调入'],
            // 出库（销售）
            ['transaction_number' => 'IT202602003', 'product_id' => $p1, 'warehouse_id' => $wh1, 'transaction_type' => 'out', 'quantity' => 5, 'unit' => '台', 'unit_cost' => 2800, 'total_cost' => 14000, 'reference_type' => 'sale', 'reference_id' => $so1, 'reason' => '销售出库', 'notes' => 'SO202602001'],
            ['transaction_number' => 'IT202602006', 'product_id' => $p3, 'warehouse_id' => $wh2, 'transaction_type' => 'out', 'quantity' => 24, 'unit' => '件', 'unit_cost' => 80, 'total_cost' => 1920, 'reference_type' => 'sale', 'reference_id' => $so2, 'reason' => '销售出库', 'notes' => 'SO202602002'],
            ['transaction_number' => 'IT202602007', 'product_id' => $p4, 'warehouse_id' => $wh1, 'transaction_type' => 'out', 'quantity' => 20, 'unit' => '个', 'unit_cost' => 25, 'total_cost' => 500, 'reference_type' => 'sale', 'reference_id' => $so3, 'reason' => '销售出库', 'notes' => 'SO202602003'],
            // 调拨出库
            ['transaction_number' => 'IT202602008', 'product_id' => $p1, 'warehouse_id' => $wh1, 'transaction_type' => 'out', 'quantity' => 5, 'unit' => '台', 'unit_cost' => 2800, 'total_cost' => 14000, 'reference_type' => 'transfer', 'reference_id' => 1, 'reason' => '调拨出库', 'notes' => '调拨至上海仓'],
            // 调整
            ['transaction_number' => 'IT202602004', 'product_id' => $p4, 'warehouse_id' => $wh1, 'transaction_type' => 'adjust', 'quantity' => -5, 'unit' => '个', 'unit_cost' => 15, 'total_cost' => -75, 'reference_type' => 'adjustment', 'reference_id' => 2, 'reason' => '报损调整', 'notes' => 'IA202602002'],
        ];

        foreach ($records as $r) {
            InventoryTransaction::firstOrCreate(
                ['transaction_number' => $r['transaction_number']],
                array_merge($r, ['created_by' => $userId])
            );
        }
    }
}
