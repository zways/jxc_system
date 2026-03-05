<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * 支付配置、创建订单与异步回调（支付宝/微信）
 */
class PaymentController extends Controller
{
    public function __construct(
        protected PaymentGatewayService $payment
    ) {}

    /**
     * 获取支付配置（是否启用、渠道、套餐与周期、未启用时的提示）
     */
    public function config(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->payment->getConfig(),
        ]);
    }

    /**
     * 创建订阅支付订单（企业续费/升级），返回支付跳转表单或二维码链接
     */
    public function createOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'plan' => 'required|string|in:basic,pro,enterprise',
            'period' => 'sometimes|string|in:1_month,3_months,1_year',
            'return_url' => 'nullable|string|url',
        ]);
        $storeId = (int) $validated['store_id'];
        $plan = $validated['plan'];
        $period = $validated['period'] ?? '1_month';
        $returnUrl = $validated['return_url'] ?? null;

        $result = $this->payment->createSubscriptionOrder($storeId, $plan, $period, $returnUrl);
        $status = $result['success'] ? 200 : 422;
        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? '',
            'data' => [
                'need_offline' => $result['need_offline'] ?? false,
                'order_no' => $result['order_no'] ?? null,
                'pay_url' => $result['pay_url'] ?? null,
                'pay_form_html' => $result['pay_form_html'] ?? null,
            ],
        ], $status);
    }

    /**
     * 支付宝异步通知（支付宝服务器 POST 到该 URL，无需鉴权）
     */
    public function notifyAlipay(Request $request): Response|JsonResponse
    {
        $result = $this->payment->handleNotify('alipay', $request);
        if ($result['handled'] && $result['response_body'] === null) {
            return response($result['message'] === 'OK' ? 'success' : 'fail', 200, ['Content-Type' => 'text/plain']);
        }
        return response('fail', 400, ['Content-Type' => 'text/plain']);
    }

    /**
     * 微信支付异步通知（微信服务器 POST JSON body，需验签并返回 JSON）
     */
    public function notifyWechat(Request $request): JsonResponse|Response
    {
        $result = $this->payment->handleNotify('wechat', $request);
        $body = $result['response_body'] ?? json_encode(['code' => 'FAIL', 'message' => $result['message'] ?? 'error']);
        $code = ($result['handled'] && str_contains($body, 'SUCCESS')) ? 200 : 500;
        return response($body, $code, ['Content-Type' => 'application/json']);
    }
}
