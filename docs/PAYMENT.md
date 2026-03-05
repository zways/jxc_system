# 支付接入说明

配置好参数并开通（`PAYMENT_ENABLED=true`）后，**支付流程可以直接跑通**，无需改代码。需满足以下条件。

## 一、必须满足的条件

### 1. 环境与配置

- **支付宝**
  - 必填：`ALIPAY_APP_ID`、`ALIPAY_PRIVATE_KEY`、`ALIPAY_PUBLIC_KEY`、`ALIPAY_NOTIFY_URL`、`ALIPAY_RETURN_URL`
  - 私钥/公钥：应用私钥（PEM）、支付宝公钥；可为 PEM 内容或 `file://` 路径
  - 若使用**沙箱**：`ALIPAY_SANDBOX=true`（默认），网关会走沙箱
- **微信支付**
  - 必填：`WECHAT_PAY_APP_ID`、`WECHAT_PAY_MCH_ID`、`WECHAT_PAY_API_V3_KEY`、`WECHAT_PAY_MERCHANT_PRIVATE_KEY`、`WECHAT_PAY_MERCHANT_SERIAL_NO`、`WECHAT_PAY_PLATFORM_PUBLIC_KEY_ID`、`WECHAT_PAY_PLATFORM_PUBLIC_KEY`、`WECHAT_PAY_NOTIFY_URL`
  - 商户证书序列号、APIv3 密钥、平台公钥等需在微信商户平台「API 安全」中获取

### 2. 回调地址可访问

- 支付宝/微信会 **POST** 到你的服务器，必须从外网可访问：
  - 支付宝：`ALIPAY_NOTIFY_URL`（如 `https://你的域名/api/v1/payment/notify/alipay`）
  - 微信：`WECHAT_PAY_NOTIFY_URL`（如 `https://你的域名/api/v1/payment/notify/wechat`）
- 本地开发可用内网穿透（如 ngrok）暴露该 URL，并在支付宝/微信后台配置同一地址

### 3. 前端或测试方式

- **创建订单**：调用 `POST /api/v1/payment/create-order`（需登录），传 `store_id`、`plan`（basic/pro/enterprise）、`period`（可选）、`return_url`（支付宝建议传）
- **支付宝**：接口返回 `pay_form_html`，前端需将该 HTML 写入页面并提交（或新窗口打开），才会跳转到支付宝收银台
- **微信**：接口返回 `pay_url`（即 code_url），前端用二维码组件展示，用户扫码支付

没有现成「续费页」时，可用 Postman/curl 调 `create-order`，把返回的 `pay_form_html` 存成 HTML 文件用浏览器打开（支付宝），或把 `pay_url` 用二维码生成器生成后扫码（微信），即可验证是否跑通。

---

## 二、可能遇到的问题

### 支付宝报错与「证书」相关

- 部分应用或环境要求使用**证书模式**。在 `.env` 中增加并填写三项证书路径（PEM 或 crt，可为 `file://` 绝对路径）：
  - `ALIPAY_MERCHANT_CERT_PATH` 应用公钥证书
  - `ALIPAY_CERT_PATH` 支付宝公钥证书
  - `ALIPAY_ROOT_CERT_PATH` 支付宝根证书
- 配置后程序会优先走证书模式，一般即可正常下单与回调。

### 微信 Native 下单失败

- 确认 `WECHAT_PAY_MERCHANT_SERIAL_NO` 与商户 API 证书序列号一致
- 确认 `WECHAT_PAY_PLATFORM_PUBLIC_KEY` 用于验签（回调与响应），未配置会导致验签失败

### 回调未触发或验签失败

- 确认通知 URL 从外网可访问，且与支付宝/微信后台配置完全一致（含 https、路径、无多余斜杠）
- 支付宝：验签使用「支付宝公钥」或证书模式下的公钥，与当前模式一致
- 微信：确认 APIv3 密钥、平台公钥正确，且回调 body 未被中间件改写（需原始 POST body）

---

## 三、自检清单（配置后直接跑通）

- [ ] `PAYMENT_ENABLED=true`，`PAYMENT_PROVIDER=alipay` 或 `wechat`
- [ ] 对应渠道的 .env 必填项已填且无误
- [ ] 支付宝：`return_url` 在 create-order 或配置中已设置
- [ ] 通知 URL 已配置且外网可访问
- [ ] 前端或测试方式会调 `create-order`，并根据返回展示表单（支付宝）或二维码（微信）

满足以上后，支付从下单到回调更新订阅可以**直接跑通**，无需改代码。
