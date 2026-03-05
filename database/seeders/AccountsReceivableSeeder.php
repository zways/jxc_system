<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AccountsReceivable;
use App\Models\Customer;
use App\Models\SalesOrder;

class AccountsReceivableSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $so1 = SalesOrder::where('order_number', 'SO202602001')->first();
        $so2 = SalesOrder::where('order_number', 'SO202602002')->first();
        $so3 = SalesOrder::where('order_number', 'SO202602003')->first();
        if (!$so1 || !$so2 || !$so3) {
            return;
        }

        $cust1 = Customer::where('customer_code', 'CUST001')->first()?->id ?? $so1->customer_id;
        $cust2 = Customer::where('customer_code', 'CUST002')->first()?->id ?? $so2->customer_id;
        $cust3 = Customer::where('customer_code', 'CUST003')->first()?->id ?? $so3->customer_id;

        $records = [
            ['customer_id' => $cust1, 'document_type' => 'sale', 'document_id' => $so1->id, 'document_date' => $so1->order_date, 'amount' => 35000, 'paid_amount' => 35000, 'balance' => 0, 'due_date' => $so1->order_date->addDays(30), 'status' => 'paid', 'notes' => '百联订单已回款'],
            ['customer_id' => $cust2, 'document_type' => 'sale', 'document_id' => $so2->id, 'document_date' => $so2->order_date, 'amount' => 3500, 'paid_amount' => 3500, 'balance' => 0, 'due_date' => $so2->order_date->addDays(14), 'status' => 'paid', 'notes' => '已结清'],
            ['customer_id' => $cust3, 'document_type' => 'sale', 'document_id' => $so3->id, 'document_date' => $so3->order_date, 'amount' => 700, 'paid_amount' => 300, 'balance' => 400, 'due_date' => $so3->order_date->addDays(15), 'status' => 'partial', 'notes' => '待收尾款'],
        ];

        foreach ($records as $r) {
            AccountsReceivable::firstOrCreate(
                [
                    'document_type' => $r['document_type'],
                    'document_id' => $r['document_id'],
                ],
                $r
            );
        }
    }
}
