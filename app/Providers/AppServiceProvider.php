<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Observers\CacheInvalidationObserver;
use App\Observers\PermissionCacheObserver;
use App\Services\PermissionCacheService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * 需要审计的模型列表
     */
    private array $auditedModels = [
        \App\Models\PurchaseOrder::class,
        \App\Models\SalesOrder::class,
        \App\Models\Customer::class,
        \App\Models\Supplier::class,
        \App\Models\Product::class,
        \App\Models\ProductCategory::class,
        \App\Models\Warehouse::class,
        \App\Models\Department::class,
        \App\Models\Store::class,
        \App\Models\User::class,
        \App\Models\Role::class,
        \App\Models\FinancialTransaction::class,
        \App\Models\AccountPayable::class,
        \App\Models\AccountReceivable::class,
        \App\Models\InventoryAdjustment::class,
        \App\Models\InventoryCount::class,
        \App\Models\InventoryTransaction::class,
        \App\Models\SalesReturn::class,
        \App\Models\ExchangeRecord::class,
        \App\Models\BusinessAgent::class,
        \App\Models\Unit::class,
    ];

    /**
     * 需要缓存失效的模型列表（CacheInvalidationObserver）
     */
    private array $cachedModels = [
        \App\Models\Product::class,
        \App\Models\ProductCategory::class,
        \App\Models\Customer::class,
        \App\Models\Supplier::class,
        \App\Models\Warehouse::class,
        \App\Models\Role::class,
        \App\Models\Unit::class,
        \App\Models\Department::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── 审计观察者（异步写入队列） ──────────────
        foreach ($this->auditedModels as $modelClass) {
            $modelClass::observe(AuditObserver::class);
        }

        // ── 缓存失效观察者（业务数据变更时清除 Redis 缓存） ──
        foreach ($this->cachedModels as $modelClass) {
            $modelClass::observe(CacheInvalidationObserver::class);
        }

        // ── 权限缓存失效 ──────────────────────────
        // 角色变更（属性修改、删除）时清除该角色下所有用户的权限缓存
        Role::observe(PermissionCacheObserver::class);

        // 用户 role_id 变更时清除该用户的权限缓存
        User::saved(function (User $user) {
            if ($user->wasChanged('role_id')) {
                PermissionCacheService::forgetUser($user->id);
            }
        });

        // 找回密码邮件：链接指向前端 SPA，正文使用中文
        ResetPassword::createUrlUsing(function ($notifiable, $token) {
            $email = $notifiable->getEmailForPasswordReset();
            $base = config('auth.frontend_url', config('app.url'));
            return rtrim($base, '/') . '/reset-password?token=' . urlencode($token) . '&email=' . urlencode($email);
        });
        ResetPassword::toMailUsing(function ($notifiable, $token) {
            $url = rtrim(config('auth.frontend_url', config('app.url')), '/')
                . '/reset-password?token=' . urlencode($token) . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
            $expire = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);
            return (new MailMessage)
                ->subject('重置密码 - ' . config('app.name'))
                ->line('您收到此邮件是因为我们收到了您账户的密码重置请求。')
                ->action('重置密码', $url)
                ->line('此链接将在 ' . $expire . ' 分钟后失效。')
                ->line('如果您未请求重置密码，请忽略此邮件。');
        });

        // ──────────────────────────────
        //  API 限流策略
        // ──────────────────────────────

        /**
         * 全局 API 限流：120 次/分钟（按已登录用户 ID 或 IP 限流）
         * 适用于所有已认证的业务接口，避免列表/弹窗/下拉等正常操作触发限流
         */
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id
                ? 'user:' . $request->user()->id
                : 'ip:' . $request->ip();

            return Limit::perMinute(120)->by($key)->response(function (Request $request, array $headers) {
                return response()->json([
                    'success' => false,
                    'message' => '请求过于频繁，请稍后再试',
                ], 429, $headers);
            });
        });

        /**
         * 登录接口限流：10 次/分钟（按 IP 限流）
         * 防止暴力破解（与 LoginThrottleService 的账号锁定互补）
         * 本地/测试环境放宽至 60 次/分钟，满足 E2E 测试多账户轮换登录
         */
        RateLimiter::for('login', function (Request $request) {
            $limit = app()->environment('testing', 'local') ? 60 : 10;
            return Limit::perMinute($limit)->by('login:' . $request->ip())->response(function (Request $request, array $headers) {
                return response()->json([
                    'success' => false,
                    'message' => '登录请求过于频繁，请 1 分钟后再试',
                ], 429, $headers);
            });
        });

        /**
         * 注册接口限流：5 次/小时（按 IP 限流）
         * 防止批量注册恶意企业
         */
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(5)->by('register:' . $request->ip())->response(function (Request $request, array $headers) {
                return response()->json([
                    'success' => false,
                    'message' => '注册请求过于频繁，请稍后再试',
                ], 429, $headers);
            });
        });

        /**
         * 找回密码接口限流：5 次/分钟（按 IP 限流）
         * 防止滥用邮件与枚举邮箱
         */
        RateLimiter::for('password_reset', function (Request $request) {
            return Limit::perMinute(5)->by('password_reset:' . $request->ip())->response(function (Request $request, array $headers) {
                return response()->json([
                    'success' => false,
                    'message' => '操作过于频繁，请 1 分钟后再试',
                ], 429, $headers);
            });
        });

        /**
         * 报表/导出接口限流：10 次/分钟（按用户 ID 限流）
         * 报表查询较重，单独限流保护后端资源
         */
        RateLimiter::for('reports', function (Request $request) {
            $key = $request->user()?->id
                ? 'report:user:' . $request->user()->id
                : 'report:ip:' . $request->ip();

            return Limit::perMinute(10)->by($key)->response(function (Request $request, array $headers) {
                return response()->json([
                    'success' => false,
                    'message' => '报表请求过于频繁，请稍后再试',
                ], 429, $headers);
            });
        });
    }
}
