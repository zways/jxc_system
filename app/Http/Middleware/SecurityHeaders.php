<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 安全响应头中间件
 *
 * 为所有响应添加安全相关 HTTP 头，防范常见的 Web 攻击：
 * - 点击劫持（X-Frame-Options）
 * - XSS（X-XSS-Protection、Content-Security-Policy）
 * - MIME 嗅探（X-Content-Type-Options）
 * - 信息泄露（X-Powered-By、Server）
 * - HTTPS 降级（Strict-Transport-Security）
 * - 引用来源泄露（Referrer-Policy）
 * - 浏览器特性滥用（Permissions-Policy）
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // 防止页面被嵌入 iframe（点击劫持防护）
        $response->headers->set('X-Frame-Options', 'DENY');

        // 防止浏览器 MIME 嗅探（避免把非脚本文件当脚本执行）
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // 启用浏览器 XSS 过滤器（旧版浏览器兼容）
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // 控制引用来源信息的发送策略
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 限制浏览器特性访问（如摄像头、麦克风、地理位置等）
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // HTTPS 严格传输安全（仅在生产环境启用，防止 HTTPS 降级攻击）
        // max-age=31536000 即一年；includeSubDomains 覆盖子域名
        if (app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // 移除可能泄露服务端技术栈的头
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        // 内容安全策略 — 仅允许同源资源，防范 XSS 注入
        // 说明：如果前端使用了 CDN 资源，需要在 script-src / style-src 中补充对应域名
        // 开发环境需要允许 Vite 开发服务器 (localhost:5173) 的资源加载
        $viteDev = app()->environment('local') ? ' http://localhost:5173 ws://localhost:5173' : '';
        $response->headers->set(
            'Content-Security-Policy',
            implode('; ', [
                "default-src 'self'" . $viteDev,
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'" . $viteDev,  // Vue SPA 需要 unsafe-inline/eval
                "style-src 'self' 'unsafe-inline'" . $viteDev,                 // Element Plus 内联样式
                "img-src 'self' data: blob:",                                   // 允许 data URI 和 blob 图片
                "font-src 'self' data:",                                        // 允许 data URI 字体
                "connect-src 'self'" . $viteDev,                                // API 请求仅限同源；开发时需连 Vite HMR
                "frame-ancestors 'none'",                                       // 禁止被嵌入 iframe
                "base-uri 'self'",                                              // 限制 <base> 标签
                "form-action 'self'",                                           // 限制表单提交目标
            ])
        );

        return $response;
    }
}
