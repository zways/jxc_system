<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AutoRbacPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $resource = $this->getResourceFromPath($request);
        if (!$resource) {
            // 没有资源段（理论不会发生），放行
            return $next($request);
        }

        $action = $this->getActionFromRequest($request);
        $permission = "{$resource}.{$action}";

        if (!$user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'data' => [
                    'required_permission' => $permission,
                ],
            ], 403);
        }

        return $next($request);
    }

    private function getResourceFromPath(Request $request): ?string
    {
        $path = trim($request->path(), '/'); // api/v1/xxx
        $segments = $path === '' ? [] : explode('/', $path);

        // 期望: api/v1/{resource}/...
        if (count($segments) < 3) return null;
        if ($segments[0] !== 'api') return null;
        if ($segments[1] !== 'v1') return null;

        return $segments[2] ?: null;
    }

    private function getActionFromRequest(Request $request): string
    {
        $method = strtoupper($request->method());
        $path = '/' . trim($request->path(), '/');

        // 特殊动作：例如 inventory-counts/{id}/complete 是“更新类”动作
        if ($method === 'POST' && str_ends_with($path, '/complete')) {
            return 'update';
        }

        return match ($method) {
            'GET', 'HEAD' => 'read',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'read',
        };
    }
}

