<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // 每个请求开始时重置企业上下文，确保干净状态
        // （在测试环境中，多个请求共享同一进程，静态状态会保留）
        TenantContext::reset();

        $token = $this->extractBearerToken($request);

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $tokenHash = hash('sha256', $token);
        $apiToken = ApiToken::query()
            ->with('user.role.permissions')
            ->where('token_hash', $tokenHash)
            ->first();

        if (!$apiToken) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($apiToken->expires_at && $apiToken->expires_at->isPast()) {
            $apiToken->delete();
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user = $apiToken->user;
        if (!$user || $user->trashed() || $user->status === 'disabled') {
            $apiToken->delete();
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // 绑定当前请求用户
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_token', $apiToken);

        // 激活企业上下文（多企业核心）
        $isSuperAdmin = ($user->role?->code ?? null) === 'super_admin';
        TenantContext::activate($user->store_id, $isSuperAdmin);

        // 记录使用时间（轻量更新）
        $apiToken->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if (!$header) return null;

        if (Str::startsWith($header, 'Bearer ')) {
            $raw = trim(Str::after($header, 'Bearer '));
            return $raw !== '' ? $raw : null;
        }

        return null;
    }
}

