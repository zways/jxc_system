<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;

class InventoryAdjustmentsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $userId = User::first()?->id ?? 1;
        $wh1 = Warehouse::where('code', 'WH001')->first()?->id ?? 1;
        $wh2 = Warehouse::where('code', 'WH002')->first()?->id ?? 2;
        $p1 = Product::where('code', 'PROD001')->first()?->id ?? 1;
        $p2 = Product::where('code', 'PROD002')->first()?->id ?? 2;
        $p4 = Product::where('code', 'PROD004')->first()?->id ?? 4;

        $records = [
            ['adjustment_number' => 'IA202602001', 'product_id' => $p1, 'warehouse_id' => $wh1, 'quantity' => 10, 'adjustment_type' => 'increase', 'adjustment_reason' => '采购入库盘盈', 'adjustment_date' => now()->subDays(5), 'adjusted_by' => $userId, 'status' => 'approved', 'notes' => '盘点发现多10台'],
            ['adjustment_number' => 'IA202602002', 'product_id' => $p4, 'warehouse_id' => $wh1, 'quantity' => -5, 'adjustment_type' => 'decrease', 'adjustment_reason' => '破损报损', 'adjustment_date' => now()->subDays(4), 'adjusted_by' => $userId, 'status' => 'approved', 'notes' => '运输破损5个'],
            ['adjustment_number' => 'IA202602003', 'product_id' => $p2, 'warehouse_id' => $wh2, 'quantity' => 2, 'adjustment_type' => 'increase', 'adjustment_reason' => '调拨入库', 'adjustment_date' => now()->subDays(3), 'adjusted_by' => $userId, 'status' => 'pending', 'notes' => '待审批'],
        ];

        foreach ($records as $r) {
            InventoryAdjustment::firstOrCreate(
                ['adjustment_number' => $r['adjustment_number']],
                $r
            );
        }
    }
}
