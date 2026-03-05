<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Models\SalesOrder;
use App\Models\PurchaseOrder;

class FinancialTransactionsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $userId = User::first()?->id ?? 1;
        $so1 = SalesOrder::where('order_number', 'SO202602001')->first();
        $so2 = SalesOrder::where('order_number', 'SO202602002')->first();
        $so3 = SalesOrder::where('order_number', 'SO202602003')->first();
        $po1 = PurchaseOrder::where('order_number', 'PO202602001')->first();
        $po2 = PurchaseOrder::where('order_number', 'PO202602002')->first();
        $po3 = PurchaseOrder::where('order_number', 'PO202602003')->first();

        $records = [
            ['transaction_number' => 'FT202602001', 'transaction_date' => now()->subDays(5), 'type' => 'revenue', 'category' => '销售收入', 'amount' => 35000, 'currency' => 'CNY', 'related_model_id' => $so1?->id ?? 1, 'related_model_type' => 'App\Models\SalesOrder', 'created_by' => $userId, 'status' => 'posted', 'description' => '百联销售回款'],
            ['transaction_number' => 'FT202602002', 'transaction_date' => now()->subDays(5), 'type' => 'expense', 'category' => '采购付款', 'amount' => 33800, 'currency' => 'CNY', 'related_model_id' => $po1?->id ?? 1, 'related_model_type' => 'App\Models\PurchaseOrder', 'created_by' => $userId, 'status' => 'posted', 'description' => '华强北采购付款'],
            ['transaction_number' => 'FT202602003', 'transaction_date' => now()->subDays(4), 'type' => 'receipt', 'category' => '客户回款', 'amount' => 3500, 'currency' => 'CNY', 'related_model_id' => $so2?->id ?? 2, 'related_model_type' => 'App\Models\SalesOrder', 'created_by' => $userId, 'status' => 'posted', 'description' => '时尚服装店回款'],
            ['transaction_number' => 'FT202602004', 'transaction_date' => now()->subDays(4), 'type' => 'payment', 'category' => '供应商付款', 'amount' => 5000, 'currency' => 'CNY', 'related_model_id' => $po2?->id ?? 2, 'related_model_type' => 'App\Models\PurchaseOrder', 'created_by' => $userId, 'status' => 'posted', 'description' => '纺织品公司部分付款'],
            ['transaction_number' => 'FT202602005', 'transaction_date' => now()->subDays(3), 'type' => 'receipt', 'category' => '客户回款', 'amount' => 300, 'currency' => 'CNY', 'related_model_id' => $so3?->id ?? 3, 'related_model_type' => 'App\Models\SalesOrder', 'created_by' => $userId, 'status' => 'posted', 'description' => '便民超市部分回款'],
            ['transaction_number' => 'FT202602006', 'transaction_date' => now()->subDays(2), 'type' => 'payment', 'category' => '供应商付款', 'amount' => 410, 'currency' => 'CNY', 'related_model_id' => $po3?->id ?? 3, 'related_model_type' => 'App\Models\PurchaseOrder', 'created_by' => $userId, 'status' => 'posted', 'description' => '食品加工厂保温杯付款'],
        ];

        foreach ($records as $r) {
            FinancialTransaction::firstOrCreate(
                ['transaction_number' => $r['transaction_number']],
                $r
            );
        }
    }
}
