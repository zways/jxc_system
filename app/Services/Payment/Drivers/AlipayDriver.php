<?php

namespace App\Services\Payment\Drivers;

use Alipay\EasySDK\Kernel\Config;
use Alipay\EasySDK\Kernel\Factory;
use Alipay\EasySDK\Kernel\Util\ResponseChecker;
use App\Models\Store;
use App\Models\SubscriptionOrder;
use Illuminate\Support\Facades\Log;

class AlipayDriver
{
    public function __construct(
        protected array $config
    ) {}

    protected function getOptions(): Config
    {
        $c = $this->config;
        $options = new Config();
        $options->protocol = 'https';
        $options->gatewayHost = ! empty($c['sandbox']) ? 'openapi-sandbox.dl.alipaydev.com' : 'openapi.alipay.com';
        $options->signType = 'RSA2';
        $options->appId = $c['app_id'] ?? '';
        $options->merchantPrivateKey = $c['private_key'] ?? '';
        $options->alipayPublicKey = $c['alipay_public_key'] ?? '';
        $options->notifyUrl = $c['notify_url'] ?? '';
        // 证书模式：三项都配置时优先使用，避免公钥模式下部分环境报错
        if (! empty($c['alipay_cert_path']) && ! empty($c['alipay_root_cert_path']) && ! empty($c['merchant_cert_path'])) {
            $options->merchantCertPath = $c['merchant_cert_path'];
            $options->alipayCertPath = $c['alipay_cert_path'];
            $options->alipayRootCertPath = $c['alipay_root_cert_path'];
        }
        return $options;
    }

    /**
     * 创建电脑网站支付订单，返回表单 HTML（前端渲染后自动跳转支付宝）
     */
    public function createOrder(SubscriptionOrder $order, string $returnUrl): array
    {
        try {
            Factory::setOptions($this->getOptions());
            $subject = '订阅续费-' . (Store::availablePlans()[$order->plan]['name'] ?? $order->plan);
            $totalAmount = (string) number_format((float) $order->amount, 2, '.', '');
            $result = Factory::payment()->page()
                ->asyncNotify($this->config['notify_url'] ?? '')
                ->pay($subject, $order->out_trade_no, $totalAmount, $returnUrl);

            $checker = new ResponseChecker();
            if ($checker->success($result) && ! empty($result->body)) {
                return [
                    'success' => true,
                    'need_offline' => false,
                    'message' => '',
                    'pay_url' => null,
                    'pay_form_html' => $result->body,
                    'order_no' => $order->out_trade_no,
                ];
            }
            return [
                'success' => false,
                'need_offline' => false,
                'message' => $result->msg ?? '支付宝下单失败',
                'pay_url' => null,
                'order_no' => $order->out_trade_no,
            ];
        } catch (\Throwable $e) {
            Log::warning('Alipay create order failed', ['order' => $order->out_trade_no, 'error' => $e->getMessage()]);
            return [
                'success' => false,
                'need_offline' => false,
                'message' => '支付宝下单异常: ' . $e->getMessage(),
                'pay_url' => null,
                'order_no' => $order->out_trade_no,
            ];
        }
    }

    /**
     * 验签并解析异步通知，返回 [verified => bool, out_trade_no => ?, trade_no => ?, trade_status => ?]
     */
    public function verifyNotify(array $params): array
    {
        try {
            Factory::setOptions($this->getOptions());
            $verified = Factory::payment()->common()->verifyNotify($params);
            if (! $verified) {
                return ['verified' => false];
            }
            return [
                'verified' => true,
                'out_trade_no' => $params['out_trade_no'] ?? null,
                'trade_no' => $params['trade_no'] ?? null,
                'trade_status' => $params['trade_status'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Alipay verify notify failed', ['error' => $e->getMessage()]);
            return ['verified' => false];
        }
    }
}
