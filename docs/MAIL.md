# 邮件发送配置（国内第三方服务）

系统使用 Laravel 原生邮件功能，找回密码等邮件通过配置的 SMTP 或日志驱动发送。以下为国内常用第三方邮件服务的配置说明。

## 配置方式

在 `.env` 中设置邮件相关变量，然后执行 `php artisan config:clear`（若使用了配置缓存）。

### 阿里云邮件推送（DirectMail）

1. 登录 [阿里云邮件推送控制台](https://directmail.console.aliyun.com/)
2. 添加并验证发信域名，创建发信地址，获取 **SMTP 密码**
3. 在 `.env` 中配置（推荐 465 端口 SSL）：

```env
MAIL_MAILER=smtp
MAIL_HOST=smtpdm.aliyun.com
MAIL_PORT=465
MAIL_USERNAME=你的发信地址@你的发信域名
MAIL_PASSWORD=控制台生成的 SMTP 密码
MAIL_FROM_ADDRESS=与 MAIL_USERNAME 一致
MAIL_FROM_NAME=进销存系统
```

- 若使用 **80 端口**（不加密）：`MAIL_PORT=80` 且 `MAIL_SCHEME=smtp`
- 发信地址需与 `MAIL_USERNAME`、`MAIL_FROM_ADDRESS` 一致

### 腾讯云邮件推送（SES）

1. 登录 [腾讯云 SES 控制台](https://console.cloud.tencent.com/ses)
2. 创建发信域名、发信地址，获取 **SMTP 密码**
3. 在 `.env` 中配置：

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.qcloudmail.com
MAIL_PORT=465
MAIL_USERNAME=你的发信地址
MAIL_PASSWORD=SMTP 密码
MAIL_FROM_ADDRESS=与 MAIL_USERNAME 一致
MAIL_FROM_NAME=进销存系统
```

## 测试发信

配置完成后，可用 Artisan 命令发送测试邮件：

```bash
php artisan mail:test your@email.com
php artisan mail:test your@email.com --subject="自定义主题"
```

成功则说明 SMTP 配置正确；若失败请根据报错检查主机、端口、用户名/密码及防火墙。

## 开发环境

未配置真实 SMTP 时，默认 `MAIL_MAILER=log`，邮件内容会写入 `storage/logs/laravel.log`，不会真实发送，便于本地调试。
