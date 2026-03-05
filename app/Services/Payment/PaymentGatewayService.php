<?php

namespace App\Services\Payment;

use App\Models\Store;
use App\Models\SubscriptionOrder;
use App\Services\Payment\Drivers\AlipayDriver;
use App\Services\Payment\Drivers\WechatPayDriver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService implements PaymentGatewayInterface
{
    public function isEnabled(): bool
    {
        return (bool) Config::get('payment.enabled', false);
    }

    public function getConfig(): array
    {
        $enabled = $this->isEnabled();
        $provider = Config::get('payment.provider', 'alipay');
        $plans = Config::get('payment.plans', []);
        $periods = Config::get('payment.periods', []);

        return [
            'enabled' => $enabled,
            'provider' => $enabled ? $provider : null,
            'offline_message' => $enabled ? null : '当前未开启在线支付，请联系管理员线下付款后由管理员为您开通/续费。',
            'plans' => $plans,
            'periods' => $periods,
        ];
    }

    protected function getDriver(): AlipayDriver|WechatPayDriver|null
    {
        $provider = Config::get('payment.provider', 'alipay');
        $key = 'payment.' . $provider;
        $config = Config::get($key, []);
        if (empty($config)) {
            return null;
        }
        return match ($provider) {
            'alipay' => new AlipayDriver($config),
            'wechat' => new WechatPayDriver($config),
            default => null,
        };
    }

    protected function computeAmount(string $plan, string $period): float
    {
        $plans = Config::get('payment.plans', []);
        $periods = Config::get('payment.periods', []);
        $planConfig = $plans[$plan] ?? null;
        $periodConfig = $periods[$period] ?? ['months' => 1, 'discount' => 1];
        if (! $planConfig) {
            return 0;
        }
        $price = (float) ($planConfig['price'] ?? 0);
        $months = (int) ($periodConfig['months'] ?? 1);
        $discount = (float) ($periodConfig['discount'] ?? 1);
        return round($price * $months * $discount, 2);
    }

    /**
     * 创建订阅订单并调起支付（支付宝返回表单 HTML，微信返回 code_url）
     */
    public function createSubscriptionOrder(int $storeId, string $plan, string $period = '1_month', ?string $returnUrl = null): array
    {
        if (! $this->isEnabled()) {
            return [
                'success' => false,
                'need_offline' => true,
                'message' => '当前未开启在线支付，请联系管理员线下付款后由管理员为您开通/续费。',
                'pay_url' => null,
                'pay_form_html' => null,
                'order_no' => null,
            ];
        }

        $store = Store::find($storeId);
        if (! $store || ! $store->is_tenant) {
            return [
                'success' => false,
                'need_offline' => false,
                'message' => '企业不存在',
                'pay_url' => null,
                'pay_form_html' => null,
                'order_no' => null,
            ];
        }

        $plans = Config::get('payment.plans', []);
        if (! isset($plans[$plan])) {
            return [
                'success' => false,
                'need_offline' => false,
                'message' => '无效套餐',
                'pay_url' => null,
                'pay_form_html' => null,
                'order_no' => null,
            ];
        }

        $driver = $this->getDriver();
        if (! $driver) {
            return [
                'success' => false,
                'need_offline' => false,
                'message' => '支付渠道未配置',
                'pay_url' => null,
                'pay_form_html' => null,
                'order_no' => null,
            ];
        }

        $amount = $this->computeAmount($plan, $period);
        if ($amount <= 0) {
            return [
                'success' => false,
                'need_offline' => false,
                'message' => '该套餐暂不支持在线支付',
                'pay_url' => null,
                'pay_form_html' => null,
                'order_no' => null,
            ];
        }

        $provider = Config::get('payment.provider', 'alipay');
        $order = SubscriptionOrder::create([
            'store_id' => $storeId,
            'out_trade_no' => SubscriptionOrder::generateOutTradeNo(),
            'plan' => $plan,
            'period' => $period,
            'amount' => $amount,
            'currency' => 'CNY',
            'channel' => $provider,
            'status' => 'pending',
        ]);

        if ($driver instanceof AlipayDriver) {
            $returnUrl = $returnUrl ?: Config::get('payment.alipay.return_url', '');
            return $driver->createOrder($order, $returnUrl);
        }
        return $driver->createOrder($order);
    }

    /**
     * 处理支付异步通知（支付宝/微信回调），验签后更新订单与企业订阅
     */
    public function handleNotify(string $provider, Request $request): array
    {
        if (! $this->isEnabled()) {
            return ['handled' => false, 'message' => 'payment disabled', 'response_body' => null];
        }

        $driver = $provider === 'wechat' ? new WechatPayDriver(Config::get('payment.wechat', [])) : new AlipayDriver(Config::get('payment.alipay', []));

        if ($provider === 'alipay') {
            $params = $request->all();
            $result = $driver->verifyNotify($params);
            $outTradeNo = $result['out_trade_no'] ?? null;
            $channelTradeNo = $result['trade_no'] ?? null;
            $paid = ($result['trade_status'] ?? '') === 'TRADE_SUCCESS';
        } else {
            $body = $request->getContent();
            $headers = $request->headers->all();
            $result = $driver->verifyNotify($body, $headers);
            $outTradeNo = $result['out_trade_no'] ?? null;
            $channelTradeNo = $result['transaction_id'] ?? null;
            $paid = ($result['trade_state'] ?? '') === 'SUCCESS';
        }

        if (! ($result['verified'] ?? false) || ! $outTradeNo) {
            return ['handled' => false, 'message' => 'verify failed', 'response_body' => $provider === 'wechat' ? json_encode(['code' => 'FAIL', 'message' => '验签失败']) : null];
        }

        $order = SubscriptionOrder::where('out_trade_no', $outTradeNo)->first();
        if (! $order) {
            Log::warning('Payment notify: order not found', ['out_trade_no' => $outTradeNo]);
            return $this->notifyResponse($provider, true, 'order not found');
        }
        if ($order->status === 'paid') {
            return $this->notifyResponse($provider, true, 'already paid');
        }
        if (! $paid) {
            return $this->notifyResponse($provider, true, 'not paid');
        }

        try {
            DB::transaction(function () use ($order, $channelTradeNo) {
                $order->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'channel_trade_no' => $channelTradeNo,
                    'raw_notify' => [],
                ]);
                $store = $order->store;
                if (! $store) {
                    return;
                }
                $periods = Config::get('payment.periods', []);
                $periodConfig = $periods[$order->period] ?? ['months' => 1];
                $months = (int) ($periodConfig['months'] ?? 1);
                $start = $store->expires_at && $store->expires_at->isFuture()
                    ? $store->expires_at
                    : Carbon::today();
                $newExpiresAt = $start->copy()->addMonths($months);
                $plans = Store::availablePlans();
                $planConfig = $plans[$order->plan] ?? null;
                $maxUsers = $planConfig['max_users'] ?? $store->max_users;
                $store->update([
                    'plan' => $order->plan,
                    'expires_at' => $newExpiresAt,
                    'max_users' => $maxUsers,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Payment notify: update failed', ['out_trade_no' => $outTradeNo, 'error' => $e->getMessage()]);
            return $this->notifyResponse($provider, false, $e->getMessage());
        }

        return $this->notifyResponse($provider, true, 'OK');
    }

    protected function notifyResponse(string $provider, bool $success, string $message): array
    {
        $body = null;
        if ($provider === 'wechat') {
            $body = json_encode([
                'code' => $success ? 'SUCCESS' : 'FAIL',
                'message' => $message,
            ]);
        }
        return [
            'handled' => true,
            'message' => $message,
            'response_body' => $body,
        ];
    }
}
