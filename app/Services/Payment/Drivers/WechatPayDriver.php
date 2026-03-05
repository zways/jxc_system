<?php

namespace App\Services\Payment\Drivers;

use App\Models\Store;
use App\Models\SubscriptionOrder;
use Illuminate\Support\Facades\Log;
use WeChatPay\Builder;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

class WechatPayDriver
{
    public function __construct(
        protected array $config
    ) {}

    protected function buildClient(): \WeChatPay\BuilderChainable
    {
        $c = $this->config;
        $mchId = $c['mch_id'] ?? '';
        $serial = $c['merchant_serial_no'] ?? '';
        $privateKey = $c['merchant_private_key'] ?? '';
        if (strpos($privateKey, '-----') !== 0 && is_file($privateKey)) {
            $privateKey = 'file://' . $privateKey;
        }
        $privateKeyInstance = Rsa::from($privateKey, Rsa::KEY_TYPE_PRIVATE);

        $config = [
            'mchid' => $mchId,
            'serial' => $serial,
            'privateKey' => $privateKeyInstance,
            'certs' => [],
        ];
        $platformId = $c['platform_public_key_id'] ?? null;
        $platformKey = $c['platform_public_key'] ?? null;
        if ($platformId && $platformKey) {
            if (strpos($platformKey, '-----') !== 0 && is_file($platformKey)) {
                $platformKey = 'file://' . $platformKey;
            }
            $config['certs'][$platformId] = Rsa::from($platformKey, Rsa::KEY_TYPE_PUBLIC);
        }

        return Builder::factory($config);
    }

    /**
     * Native 下单（扫码支付），返回 code_url 供前端生成二维码
     */
    public function createOrder(SubscriptionOrder $order): array
    {
        try {
            $client = $this->buildClient();
            $appId = $this->config['app_id'] ?? '';
            $mchId = $this->config['mch_id'] ?? '';
            $notifyUrl = $this->config['notify_url'] ?? '';
            $description = '订阅续费-' . (Store::availablePlans()[$order->plan]['name'] ?? $order->plan);
            $totalFen = (int) round((float) $order->amount * 100);

            $resp = $client->chain('v3/pay/transactions/native')
                ->post([
                    'json' => [
                        'appid' => $appId,
                        'mchid' => $mchId,
                        'description' => $description,
                        'out_trade_no' => $order->out_trade_no,
                        'notify_url' => $notifyUrl,
                        'amount' => [
                            'total' => $totalFen,
                            'currency' => 'CNY',
                        ],
                    ],
                ]);

            $statusCode = $resp->getStatusCode();
            $body = (string) $resp->getBody();
            $data = json_decode($body, true);
            if ($statusCode >= 200 && $statusCode < 300 && ! empty($data['code_url'])) {
                return [
                    'success' => true,
                    'need_offline' => false,
                    'message' => '',
                    'pay_url' => $data['code_url'],
                    'pay_form_html' => null,
                    'order_no' => $order->out_trade_no,
                ];
            }
            return [
                'success' => false,
                'need_offline' => false,
                'message' => $data['message'] ?? $data['code'] ?? '微信下单失败',
                'pay_url' => null,
                'order_no' => $order->out_trade_no,
            ];
        } catch (\Throwable $e) {
            Log::warning('WeChat create order failed', ['order' => $order->out_trade_no, 'error' => $e->getMessage()]);
            return [
                'success' => false,
                'need_offline' => false,
                'message' => '微信下单异常: ' . $e->getMessage(),
                'pay_url' => null,
                'order_no' => $order->out_trade_no,
            ];
        }
    }

    /**
     * 验签并解密密文，返回 [verified => bool, out_trade_no => ?, transaction_id => ?, trade_state => ?]
     */
    public function verifyNotify(string $body, array $headers): array
    {
        $c = $this->config;
        $signature = $headers['Wechatpay-Signature'] ?? $headers['wechatpay-signature'] ?? '';
        $timestamp = $headers['Wechatpay-Timestamp'] ?? $headers['wechatpay-timestamp'] ?? '';
        $nonce = $headers['Wechatpay-Nonce'] ?? $headers['wechatpay-nonce'] ?? '';
        $serial = $headers['Wechatpay-Serial'] ?? $headers['wechatpay-serial'] ?? '';
        $apiv3Key = $c['api_v3_key'] ?? '';

        if (! $signature || ! $timestamp || ! $nonce || ! $body || ! $apiv3Key) {
            return ['verified' => false];
        }
        if (abs((int) $timestamp - time()) > 300) {
            return ['verified' => false];
        }

        $platformKey = $c['platform_public_key'] ?? null;
        if ($platformKey && (strpos($platformKey, '-----') !== 0) && is_file($platformKey)) {
            $platformKey = 'file://' . $platformKey;
        }
        if (! $platformKey) {
            Log::warning('WeChat notify: missing platform public key');
            return ['verified' => false];
        }
        $publicKey = Rsa::from($platformKey, Rsa::KEY_TYPE_PUBLIC);
        $verified = Rsa::verify(
            Formatter::joinedByLineFeed($timestamp, $nonce, $body),
            $signature,
            $publicKey
        );
        if (! $verified) {
            return ['verified' => false];
        }

        $arr = json_decode($body, true);
        $resource = $arr['resource'] ?? [];
        $ciphertext = $resource['ciphertext'] ?? '';
        $nonceVal = $resource['nonce'] ?? '';
        $aad = $resource['associated_data'] ?? '';
        if (! $ciphertext || ! $nonceVal) {
            return ['verified' => false];
        }
        try {
            $decrypted = AesGcm::decrypt($ciphertext, $apiv3Key, $nonceVal, $aad);
        } catch (\Throwable $e) {
            Log::warning('WeChat notify decrypt failed', ['error' => $e->getMessage()]);
            return ['verified' => false];
        }
        $data = json_decode($decrypted, true);
        return [
            'verified' => true,
            'out_trade_no' => $data['out_trade_no'] ?? null,
            'transaction_id' => $data['transaction_id'] ?? null,
            'trade_state' => $data['trade_state'] ?? null,
        ];
    }
}
