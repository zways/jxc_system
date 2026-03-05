<?php

namespace Database\Seeders;

use App\Models\BusinessAgent;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BusinessAgentSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $agents = [
            [
                'agent_code' => 'AGT0001',
                'name' => '张伟',
                'phone' => '13900000001',
                'email' => 'zhang.wei@example.com',
                'commission_rate' => 2.50,
                'territory' => '华北',
                'status' => 'active',
                'notes' => '重点客户维护',
            ],
            [
                'agent_code' => 'AGT0002',
                'name' => '王芳',
                'phone' => '13900000002',
                'email' => 'wang.fang@example.com',
                'commission_rate' => 3.00,
                'territory' => '华东',
                'status' => 'active',
                'notes' => '擅长渠道拓展',
            ],
            [
                'agent_code' => 'AGT0003',
                'name' => '李强',
                'phone' => '13900000003',
                'email' => 'li.qiang@example.com',
                'commission_rate' => 1.80,
                'territory' => '华南',
                'status' => 'active',
                'notes' => '大客户签约负责人',
            ],
            [
                'agent_code' => 'AGT0004',
                'name' => '刘洋',
                'phone' => '13900000004',
                'email' => 'liu.yang@example.com',
                'commission_rate' => 2.00,
                'territory' => '西南',
                'status' => 'active',
                'notes' => null,
            ],
            [
                'agent_code' => 'AGT0005',
                'name' => '陈静',
                'phone' => '13900000005',
                'email' => 'chen.jing@example.com',
                'commission_rate' => 4.20,
                'territory' => '华中',
                'status' => 'active',
                'notes' => '重点跟进回款',
            ],
            [
                'agent_code' => 'AGT0006',
                'name' => '赵敏',
                'phone' => '13900000006',
                'email' => 'zhao.min@example.com',
                'commission_rate' => 2.80,
                'territory' => '东北',
                'status' => 'active',
                'notes' => '零售客户为主',
            ],
            [
                'agent_code' => 'AGT0007',
                'name' => '周磊',
                'phone' => '13900000007',
                'email' => 'zhou.lei@example.com',
                'commission_rate' => 0.00,
                'territory' => '华北',
                'status' => 'inactive',
                'notes' => '停用测试数据',
            ],
            [
                'agent_code' => 'AGT0008',
                'name' => '孙丽',
                'phone' => '13900000008',
                'email' => 'sun.li@example.com',
                'commission_rate' => 5.00,
                'territory' => '华东',
                'status' => 'active',
                'notes' => '高提成样例',
            ],
            [
                'agent_code' => 'AGT0009',
                'name' => '马超',
                'phone' => '13900000009',
                'email' => 'ma.chao@example.com',
                'commission_rate' => 1.20,
                'territory' => '西北',
                'status' => 'active',
                'notes' => '新入职',
            ],
            [
                'agent_code' => 'AGT0010',
                'name' => '黄蓉',
                'phone' => '13900000010',
                'email' => 'huang.rong@example.com',
                'commission_rate' => 3.50,
                'territory' => '华南',
                'status' => 'active',
                'notes' => '维护老客户',
            ],
            [
                'agent_code' => 'AGT0011',
                'name' => '唐杰',
                'phone' => '13900000011',
                'email' => 'tang.jie@example.com',
                'commission_rate' => 2.20,
                'territory' => '西南',
                'status' => 'active',
                'notes' => null,
            ],
            [
                'agent_code' => 'AGT0012',
                'name' => '何珊',
                'phone' => '13900000012',
                'email' => 'he.shan@example.com',
                'commission_rate' => 1.00,
                'territory' => '华中',
                'status' => 'inactive',
                'notes' => '停用样例（用于筛选测试）',
            ],
        ];

        foreach ($agents as $agent) {
            // 注意：business_agents 使用了 SoftDeletes，若存在同 agent_code 的软删除数据，
            // 普通 updateOrCreate 查不到，会触发唯一索引冲突；这里用 withTrashed 并恢复数据。
            BusinessAgent::withTrashed()->updateOrCreate(
                ['agent_code' => $agent['agent_code']],
                array_merge($agent, ['deleted_at' => null])
            );
        }
    }
}

