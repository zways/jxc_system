<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 在线支付总开关
    |--------------------------------------------------------------------------
    | 设为 false 时，不调用任何支付渠道，用户需线下付款，由管理员在后台手动续费/改套餐。
    | 设为 true 时，将根据 provider 调用对应支付渠道（需配置好下方参数）。
    */
    'enabled' => env('PAYMENT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | 支付渠道
    |--------------------------------------------------------------------------
    | 可选: alipay, wechat, stripe 等。未开启时该值不生效。
    */
    'provider' => env('PAYMENT_PROVIDER', 'alipay'),

    /*
    |--------------------------------------------------------------------------
    | 支付宝（预留，启用后填写 .env）
    |--------------------------------------------------------------------------
    */
    'alipay' => [
        'app_id' => env('ALIPAY_APP_ID', ''),
        'private_key' => env('ALIPAY_PRIVATE_KEY', ''),
        'alipay_public_key' => env('ALIPAY_PUBLIC_KEY', ''),
        'notify_url' => env('ALIPAY_NOTIFY_URL', ''),
        'return_url' => env('ALIPAY_RETURN_URL', ''),
        'sandbox' => env('ALIPAY_SANDBOX', true),
        // 证书模式（可选）：若支付宝要求或公钥模式报错，可配置以下三项，优先于公钥
        'merchant_cert_path' => env('ALIPAY_MERCHANT_CERT_PATH', ''),
        'alipay_cert_path' => env('ALIPAY_CERT_PATH', ''),
        'alipay_root_cert_path' => env('ALIPAY_ROOT_CERT_PATH', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | 微信支付（预留，启用后填写 .env）
    |--------------------------------------------------------------------------
    */
    'wechat' => [
        'app_id' => env('WECHAT_PAY_APP_ID', ''),
        'mch_id' => env('WECHAT_PAY_MCH_ID', ''),
        'api_v3_key' => env('WECHAT_PAY_API_V3_KEY', ''),
        'merchant_private_key' => env('WECHAT_PAY_MERCHANT_PRIVATE_KEY', ''),
        'merchant_serial_no' => env('WECHAT_PAY_MERCHANT_SERIAL_NO', ''),
        'platform_public_key_id' => env('WECHAT_PAY_PLATFORM_PUBLIC_KEY_ID', ''),
        'platform_public_key' => env('WECHAT_PAY_PLATFORM_PUBLIC_KEY', ''),
        'notify_url' => env('WECHAT_PAY_NOTIFY_URL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | 订阅套餐与价格（用于在线支付展示与下单）
    |--------------------------------------------------------------------------
    */
    'plans' => [
        'free' => ['name' => '免费版', 'price' => 0, 'max_users' => 5],
        'basic' => ['name' => '基础版', 'price' => 99, 'max_users' => 20],
        'pro' => ['name' => '专业版', 'price' => 299, 'max_users' => 50],
        'enterprise' => ['name' => '企业版', 'price' => 999, 'max_users' => 999],
    ],

    /*
    |--------------------------------------------------------------------------
    | 订阅周期与价格系数（周期 => 月数 或 价格系数，用于计算实付金额）
    |--------------------------------------------------------------------------
    */
    'periods' => [
        '1_month' => ['months' => 1, 'discount' => 1],
        '3_months' => ['months' => 3, 'discount' => 0.95],
        '1_year' => ['months' => 12, 'discount' => 0.85],
    ],

];
