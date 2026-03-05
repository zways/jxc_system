<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExchangeRecord;
use App\Models\SalesOrder;
use App\Models\User;

class ExchangeRecordsSeeder extends Seeder
{
    public function run(): void
    {
        $userId = User::first()?->id ?? 1;
        $so3 = SalesOrder::where('order_number', 'SO202602003')->first();

        if (!$so3) {
            return;
        }

        ExchangeRecord::firstOrCreate(
            ['exchange_number' => 'EX202602001'],
            [
                'sale_id' => $so3->id,
                'customer_id' => $so3->customer_id,
                'exchange_date' => now()->toDateString(),
                'status' => 'pending',
                'reason' => '颜色不匹配换货',
                'exchanged_by' => $userId,
                'notes' => '测试换货单（待完成）',
            ]
        );
    }
}
