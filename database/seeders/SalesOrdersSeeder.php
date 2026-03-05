<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SalesOrder;
use App\Models\Customer;
use App\Models\Warehouse;
use App\Models\User;

class SalesOrdersSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $userId = User::first()?->id ?? 1;
        $wh1 = Warehouse::where('code', 'WH001')->first()?->id ?? 1;
        $wh2 = Warehouse::where('code', 'WH002')->first()?->id ?? 2;
        $cust1 = Customer::where('customer_code', 'CUST001')->first()?->id ?? 1;
        $cust2 = Customer::where('customer_code', 'CUST002')->first()?->id ?? 2;
        $cust3 = Customer::where('customer_code', 'CUST003')->first()?->id ?? 3;

        $orders = [
            ['order_number' => 'SO202602001', 'customer_id' => $cust1, 'order_date' => now()->subDays(5), 'delivery_date' => now()->subDays(4), 'subtotal' => 35000, 'discount' => 0, 'tax_amount' => 0, 'shipping_cost' => 0, 'total_amount' => 35000, 'order_type' => 'wholesale', 'status' => 'delivered', 'payment_status' => 'paid', 'delivery_status' => 'delivered', 'warehouse_id' => $wh1, 'notes' => '百联批发'],
            ['order_number' => 'SO202602002', 'customer_id' => $cust2, 'order_date' => now()->subDays(4), 'delivery_date' => now()->subDays(3), 'subtotal' => 3600, 'discount' => 100, 'tax_amount' => 0, 'shipping_cost' => 0, 'total_amount' => 3500, 'order_type' => 'retail', 'status' => 'delivered', 'payment_status' => 'paid', 'delivery_status' => 'delivered', 'warehouse_id' => $wh2, 'notes' => '时尚服装店零售'],
            ['order_number' => 'SO202602003', 'customer_id' => $cust3, 'order_date' => now()->subDays(3), 'delivery_date' => now()->subDays(2), 'subtotal' => 700, 'discount' => 0, 'tax_amount' => 0, 'shipping_cost' => 0, 'total_amount' => 700, 'order_type' => 'retail', 'status' => 'confirmed', 'payment_status' => 'partial', 'delivery_status' => 'pending', 'warehouse_id' => $wh1, 'notes' => '便民超市订单'],
        ];

        foreach ($orders as $o) {
            SalesOrder::firstOrCreate(
                ['order_number' => $o['order_number']],
                array_merge($o, ['created_by' => $userId])
            );
        }
    }
}
