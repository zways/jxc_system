<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;
use Sentry\State\Scope;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 宿主机 Nginx 反向代理时，信任代理头（X-Forwarded-*）
        // 宿主机访问容器端口时，请求可能来自 127.0.0.1 或 Docker 网桥 IP（如 172.17.0.1），故信任全部
        $middleware->trustProxies(at: '*');

        // 全局中间件：安全响应头（对所有请求生效）
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'auth.token' => \App\Http\Middleware\AuthenticateWithApiToken::class,
            'rbac.auto' => \App\Http\Middleware\AutoRbacPermission::class,
            'tenant.active' => \App\Http\Middleware\CheckTenantActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // ── Sentry 错误上报 ──────────────────────────
        Integration::handles($exceptions);

        // ── 为 Sentry 附加业务上下文（用户、企业、请求信息）──
        $exceptions->reportable(function (\Throwable $e) {
            if (app()->bound('sentry') && app('sentry')->getClient()) {
                \Sentry\configureScope(function (Scope $scope): void {
                    /** @var \App\Models\User|null $user */
                    $user = \Illuminate\Support\Facades\Auth::user();
                    if ($user) {
                        $scope->setUser([
                            'id' => (string) $user->id,
                            'username' => $user->username ?? $user->name ?? '',
                            'email' => $user->email ?? '',
                        ]);
                        $scope->setTag('tenant_id', (string) ($user->store_id ?? 'none'));
                        $scope->setTag('role', $user->role?->code ?? 'none');
                    }

                    $request = request();
                    if ($request) {
                        $scope->setTag('url', $request->fullUrl());
                        $scope->setTag('method', $request->method());
                    }
                });
            }
        })->stop(); // stop() 阻止重复上报到默认日志

        // ── 统一 API JSON 异常响应 ──────────────────────

        // 404 Not Found
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => '资源不存在',
                ], 404);
            }
        });

        // 405 Method Not Allowed
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => '请求方法不允许',
                ], 405);
            }
        });

        // 429 Too Many Requests
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => '请求过于频繁，请稍后再试',
                ], 429);
            }
        });

        // 500 Server Error（生产环境隐藏详情）
        $exceptions->renderable(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                /** @var int $status */
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

                // 只拦截 500 级别错误
                if ($status >= 500) {
                    $data = [
                        'success' => false,
                        'message' => app()->isProduction()
                            ? '服务器内部错误，请稍后再试'
                            : $e->getMessage(),
                    ];

                    // 开发环境下附带堆栈信息
                    if (!app()->isProduction()) {
                        $data['debug'] = [
                            'exception' => get_class($e),
                            'file' => $e->getFile() . ':' . $e->getLine(),
                            'trace' => array_slice($e->getTrace(), 0, 5),
                        ];
                    }

                    return response()->json($data, $status);
                }
            }
        });

    })->create();
