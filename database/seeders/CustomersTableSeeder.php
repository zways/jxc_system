<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Customer;

class CustomersTableSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = [
            [
                'customer_code' => 'CUST001',
                'name' => '百联购物中心',
                'contact_person' => '刘经理',
                'phone' => '13600136000',
                'email' => 'liu@bailian.com',
                'address' => '北京市朝阳区建国路',
                'tax_number' => '91110105123456789A',
                'credit_limit' => 100000.00,
                'customer_level' => 'VIP客户',
                'payment_terms' => '月结30天',
                'rating' => 5,
                'notes' => '大型连锁超市',
                'is_active' => true,
            ],
            [
                'customer_code' => 'CUST002',
                'name' => '时尚服装店',
                'contact_person' => '陈老板',
                'phone' => '13500135000',
                'email' => 'chen@fashion.com',
                'address' => '上海市黄浦区南京路',
                'tax_number' => '91310101123456789B',
                'credit_limit' => 20000.00,
                'customer_level' => '重要客户',
                'payment_terms' => '周结',
                'rating' => 4,
                'notes' => '高端服装零售',
                'is_active' => true,
            ],
            [
                'customer_code' => 'CUST003',
                'name' => '便民超市',
                'contact_person' => '赵老板',
                'phone' => '13400134000',
                'email' => 'zhao@supermarket.com',
                'address' => '广州市越秀区北京路',
                'tax_number' => '91440104123456789C',
                'credit_limit' => 15000.00,
                'customer_level' => '普通客户',
                'payment_terms' => '现结',
                'rating' => 3,
                'notes' => '社区便利店',
                'is_active' => true,
            ],
        ];

        foreach ($customers as $customer) {
            Customer::updateOrCreate(['customer_code' => $customer['customer_code']], $customer);
        }
    }
}
