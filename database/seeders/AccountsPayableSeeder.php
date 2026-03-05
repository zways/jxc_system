<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AccountsPayable;
use App\Models\Supplier;
use App\Models\PurchaseOrder;

class AccountsPayableSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $po1 = PurchaseOrder::where('order_number', 'PO202602001')->first();
        $po2 = PurchaseOrder::where('order_number', 'PO202602002')->first();
        $po3 = PurchaseOrder::where('order_number', 'PO202602003')->first();
        if (!$po1 || !$po2 || !$po3) {
            return;
        }

        $sup1 = Supplier::where('supplier_code', 'SUP001')->first()?->id ?? $po1->supplier_id;
        $sup2 = Supplier::where('supplier_code', 'SUP002')->first()?->id ?? $po2->supplier_id;
        $sup3 = Supplier::where('supplier_code', 'SUP003')->first()?->id ?? $po3->supplier_id;

        $records = [
            ['supplier_id' => $sup1, 'document_type' => 'purchase', 'document_id' => $po1->id, 'document_date' => $po1->order_date, 'amount' => 33800, 'paid_amount' => 33800, 'balance' => 0, 'due_date' => $po1->order_date->addDays(30), 'status' => 'paid', 'notes' => '已付清'],
            ['supplier_id' => $sup2, 'document_type' => 'purchase', 'document_id' => $po2->id, 'document_date' => $po2->order_date, 'amount' => 9400, 'paid_amount' => 5000, 'balance' => 4400, 'due_date' => $po2->order_date->addDays(30), 'status' => 'partial', 'notes' => '部分付款'],
            ['supplier_id' => $sup3, 'document_type' => 'purchase', 'document_id' => $po3->id, 'document_date' => $po3->order_date, 'amount' => 410, 'paid_amount' => 0, 'balance' => 410, 'due_date' => $po3->order_date->addDays(15), 'status' => 'unpaid', 'notes' => '待付款'],
        ];

        foreach ($records as $r) {
            AccountsPayable::firstOrCreate(
                [
                    'document_type' => $r['document_type'],
                    'document_id' => $r['document_id'],
                ],
                $r
            );
        }
    }
}
