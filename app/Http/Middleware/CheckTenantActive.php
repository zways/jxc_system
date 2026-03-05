<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 检查企业是否有效（激活 + 未过期）
 *
 * 此中间件在 auth.token 之后执行，确保：
 * 1. 超级管理员始终放行
 * 2. 企业已停用 → 403
 * 3. 企业已过期 → 403（附带过期提示）
 */
class CheckTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        // 超级管理员不受限制
        if (TenantContext::isSuperAdmin()) {
            return $next($request);
        }

        $tenantId = TenantContext::getTenantId();
        if ($tenantId === null) {
            return response()->json([
                'success' => false,
                'message' => '用户未绑定企业，请联系管理员',
            ], 403);
        }

        // 查询企业信息（绕过全局作用域，因为 Store 没有 BelongsToTenant）
        $store = Store::find($tenantId);
        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => '企业不存在',
            ], 403);
        }

        if (!$store->is_active) {
            return response()->json([
                'success' => false,
                'message' => '企业已停用，请联系管理员',
            ], 403);
        }

        if ($store->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => '企业订阅已过期，请续费后继续使用',
                'data' => [
                    'expired_at' => $store->expires_at->toDateString(),
                    'plan' => $store->plan,
                ],
            ], 403);
        }

        return $next($request);
    }
}
