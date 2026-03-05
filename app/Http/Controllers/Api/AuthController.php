<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ApiToken;
use App\Models\User;
use App\Scopes\TenantScope;
use App\Services\LoginThrottleService;
use App\Services\PermissionCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected LoginThrottleService $throttle;

    public function __construct(LoginThrottleService $throttle)
    {
        $this->throttle = $throttle;
    }

    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
                'device_name' => 'nullable|string|max:100',
            ]);

            $username = $validated['username'];
            $password = $validated['password'];
            $ip = $request->ip();

            // ---- 登录限流检查 ----
            if ($this->throttle->isLocked($username, $ip)) {
                $remaining = $this->throttle->remainingLockoutSeconds($username, $ip);
                $minutes = (int) ceil($remaining / 60);
                return response()->json([
                    'success' => false,
                    'message' => "登录失败次数过多，请 {$minutes} 分钟后再试",
                    'data' => [
                        'locked' => true,
                        'retry_after_seconds' => $remaining,
                    ],
                ], 429);
            }

            // 登录时绕过企业作用域：用户可能属于任何企业
            /** @var User|null $user */
            $user = User::withoutGlobalScope(TenantScope::class)
                ->with(['role.permissions', 'department', 'store', 'warehouse'])
                ->where('username', $username)
                ->orWhere('email', $username)
                ->first();

            if (!$user || $user->trashed() || $user->status === 'disabled') {
                // 记录失败次数
                $attempts = $this->throttle->recordFailedAttempt($username, $ip);
                $remainingAttempts = $this->throttle->remainingAttempts($username, $ip);

                $message = '账号或密码错误';
                if ($remainingAttempts > 0 && $remainingAttempts <= 3) {
                    $message .= "，还可尝试 {$remainingAttempts} 次";
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'data' => [
                        'remaining_attempts' => $remainingAttempts,
                    ],
                ], 422);
            }

            if (!Hash::check($password, $user->password)) {
                // 记录失败次数
                $attempts = $this->throttle->recordFailedAttempt($username, $ip);
                $remainingAttempts = $this->throttle->remainingAttempts($username, $ip);

                $message = '账号或密码错误';
                if ($remainingAttempts > 0 && $remainingAttempts <= 3) {
                    $message .= "，还可尝试 {$remainingAttempts} 次";
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'data' => [
                        'remaining_attempts' => $remainingAttempts,
                    ],
                ], 422);
            }

            // ---- 登录成功，清除失败计数 ----
            $this->throttle->clear($username, $ip);

            $plainToken = Str::random(64);
            $tokenHash = hash('sha256', $plainToken);

            ApiToken::create([
                'user_id' => $user->id,
                'name' => $validated['device_name'] ?? 'web',
                'token_hash' => $tokenHash,
                'abilities' => null,
                'last_used_at' => now(),
                'expires_at' => now()->addDays(7),  // Token 7天后过期
            ]);

            $user->forceFill(['last_login_at' => now()])->save();

            // 登录时预热权限缓存（避免后续请求再查库）
            PermissionCacheService::warmup($user);

            $permissions = $user->role?->permissions?->pluck('name')->values() ?? collect();

            $this->audit($request, 'login', $user, null, null, '用户登录');

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $plainToken,
                    'user' => (new UserResource($user))->resolve(),
                    'permissions' => $permissions,
                ],
                'message' => '登录成功',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '校验失败',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['role.permissions', 'department', 'store', 'warehouse']);
        $permissions = $user->role?->permissions?->pluck('name')->values() ?? collect();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => (new UserResource($user))->resolve(),
                'permissions' => $permissions,
            ],
            'message' => '操作成功',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->audit($request, 'logout', $request->user(), null, null, '用户登出');

        /** @var ApiToken|null $apiToken */
        $apiToken = $request->attributes->get('api_token');
        if ($apiToken) {
            $apiToken->delete();
        }

        return response()->json([
            'success' => true,
            'message' => '已退出登录',
        ]);
    }

    /**
     * 获取找回密码功能是否开启（公开接口，供登录页是否显示“找回密码”入口）
     */
    public function passwordResetConfig(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => config('auth.password_reset_enabled', true),
            ],
        ]);
    }

    /**
     * 发送找回密码邮件（公开接口，需配置开启）
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        if (!config('auth.password_reset_enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => '找回密码功能未开启',
            ], 403);
        }

        $validated = $request->validate([
            'email' => 'required|email',
        ]);
        $email = $validated['email'];

        $user = User::withoutGlobalScope(TenantScope::class)
            ->where('email', $email)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'disabled')
            ->first();

        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => '若该邮箱已注册，您将收到重置链接，请查收邮件',
            ]);
        }

        /** @var \Illuminate\Auth\Passwords\PasswordBroker $broker */
        $broker = Password::broker();
        $token = $broker->getRepository()->create($user);
        $user->sendPasswordResetNotification($token);

        return response()->json([
            'success' => true,
            'message' => '若该邮箱已注册，您将收到重置链接，请查收邮件',
        ]);
    }

    /**
     * 根据 token 重置密码（公开接口，需配置开启）
     */
    public function resetPassword(Request $request): JsonResponse
    {
        if (!config('auth.password_reset_enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => '找回密码功能未开启',
            ], 403);
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => ['required', 'string', 'confirmed', 'min:6'],
        ]);

        $status = Password::broker()->reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'message' => $status === Password::INVALID_TOKEN
                    ? '链接已失效或已使用，请重新申请找回密码'
                    : '无法重置密码，请重试',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => '密码已重置，请使用新密码登录',
        ]);
    }
}

