<?php

namespace App\Services\Payment;

use Illuminate\Http\Request;

/**
 * 支付网关接口（先对接好，启用后实现具体渠道）
 *
 * 未开启时：创建订单等接口返回 need_offline，由管理员后台手动续费。
 * 开启后：实现类调用支付宝/微信等，更新参数即可切换。
 */
interface PaymentGatewayInterface
{
    /**
     * 是否已启用在线支付
     */
    public function isEnabled(): bool;

    /**
     * 获取前端展示用配置（是否启用、渠道名、未启用时的提示文案）
     */
    public function getConfig(): array;

    /**
     * 创建订阅支付订单（企业续费/升级套餐）
     *
     * @param int  $storeId  企业(Store) ID
     * @param string $plan   套餐: free, basic, pro, enterprise
     * @param string $period 周期: 1_month, 3_months, 1_year
     * @param string|null $returnUrl 支付宝同步跳转 URL（仅支付宝需要）
     * @return array ['success' => bool, 'need_offline' => bool, 'message' => string, 'pay_url' => ?string, 'pay_form_html' => ?string, 'order_no' => ?string]
     */
    public function createSubscriptionOrder(int $storeId, string $plan, string $period = '1_month', ?string $returnUrl = null): array;

    /**
     * 支付回调验签并更新订阅（启用后由渠道回调调用）
     *
     * @param string $provider  alipay | wechat
     * @param Request $request 回调请求（支付宝为 form，微信为 raw body + headers）
     * @return array{handled: bool, message: string, response_body: ?string}
     */
    public function handleNotify(string $provider, Request $request): array;
}
