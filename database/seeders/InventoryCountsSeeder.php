<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\InventoryCount;
use App\Models\Warehouse;
use App\Models\User;

class InventoryCountsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $userId = User::first()?->id ?? 1;
        $wh1 = Warehouse::where('code', 'WH001')->first()?->id ?? 1;
        $wh2 = Warehouse::where('code', 'WH002')->first()?->id ?? 2;
        $wh3 = Warehouse::where('code', 'WH003')->first()?->id ?? 3;

        $records = [
            ['count_number' => 'IC202602001', 'warehouse_id' => $wh1, 'type' => 'cycle', 'count_date' => now()->subDays(5), 'counted_by' => $userId, 'status' => 'completed', 'variance_amount' => 0, 'notes' => '月度盘点'],
            ['count_number' => 'IC202602002', 'warehouse_id' => $wh2, 'type' => 'cycle', 'count_date' => now()->subDays(4), 'counted_by' => $userId, 'status' => 'completed', 'variance_amount' => -125.50, 'notes' => '上海仓盘点'],
            ['count_number' => 'IC202602003', 'warehouse_id' => $wh1, 'type' => 'frozen', 'count_date' => now(), 'counted_by' => $userId, 'status' => 'in_progress', 'variance_amount' => 0, 'notes' => '抽盘进行中'],
            ['count_number' => 'IC202602004', 'warehouse_id' => $wh3, 'type' => 'cycle', 'count_date' => now()->subDays(1), 'counted_by' => $userId, 'status' => 'completed', 'variance_amount' => 0, 'notes' => '冷链仓月度盘点'],
        ];

        foreach ($records as $r) {
            InventoryCount::firstOrCreate(
                ['count_number' => $r['count_number']],
                $r
            );
        }
    }
}
