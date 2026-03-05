<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SalesReturn;
use App\Models\SalesOrder;
use App\Models\AccountReceivable;
use App\Models\User;

class SalesReturnsSeeder extends Seeder
{
    public function run(): void
    {
        $userId = User::first()?->id ?? 1;

        $so1 = SalesOrder::where('order_number', 'SO202602001')->first();
        $so2 = SalesOrder::where('order_number', 'SO202602002')->first();

        if ($so1) {
            SalesReturn::firstOrCreate(
                ['return_number' => 'SR202602001'],
                [
                    'sale_id' => $so1->id,
                    'customer_id' => $so1->customer_id,
                    'return_date' => now()->subDays(2)->toDateString(),
                    'subtotal' => 3500,
                    'tax_amount' => 0,
                    'total_amount' => 3500,
                    'status' => 'pending',
                    'reason' => '质量问题退货',
                    'returned_by' => $userId,
                    'warehouse_id' => $so1->warehouse_id,
                    'notes' => '测试退货单（待处理）',
                ]
            );
        }

        if ($so2) {
            SalesReturn::firstOrCreate(
                ['return_number' => 'SR202602002'],
                [
                    'sale_id' => $so2->id,
                    'customer_id' => $so2->customer_id,
                    'return_date' => now()->subDays(1)->toDateString(),
                    'subtotal' => 500,
                    'tax_amount' => 0,
                    'total_amount' => 500,
                    'status' => 'approved',
                    'reason' => '尺码不合适换退',
                    'returned_by' => $userId,
                    'warehouse_id' => $so2->warehouse_id,
                    'notes' => '测试退货单（用于退款）',
                ]
            );

            AccountReceivable::firstOrCreate(
                [
                    'customer_id' => $so2->customer_id,
                    'document_type' => 'sales_order',
                    'document_id' => $so2->id,
                ],
                [
                    'document_date' => $so2->order_date?->format('Y-m-d') ?? now()->toDateString(),
                    'amount' => 3500,
                    'paid_amount' => 4000,
                    'balance' => 0,
                    'due_date' => now()->addDays(7)->toDateString(),
                    'status' => 'paid',
                    'notes' => '测试退款超收场景',
                ]
            );
        }
    }
}
