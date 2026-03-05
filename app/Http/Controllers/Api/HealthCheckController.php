<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

/**
 * 系统健康检查控制器 — 用于运维监控和告警。
 *
 * 提供两个端点：
 * - GET /api/v1/health       — 基础存活检查（无需认证）
 * - GET /api/v1/health/deep  — 深度检查（需认证 + 超管）
 */
class HealthCheckController extends Controller
{
    /**
     * 基础存活检查 — 仅检测应用是否响应
     *
     * 适用场景：负载均衡心跳探测、容器 liveness probe
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => '正常',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * 深度健康检查 — 检测所有依赖服务的连通性
     *
     * 返回各服务状态以及响应延迟（毫秒）。
     */
    public function deep(): JsonResponse
    {
        $checks = [];
        $allHealthy = true;

        // ── MySQL ────────────────────────
        $checks['database'] = $this->checkDatabase();
        if ($checks['database']['status'] !== 'ok') {
            $allHealthy = false;
        }

        // ── Redis ────────────────────────
        // 仅当 session/cache/queue 任一使用 redis 时才检查
        $redisInUse = in_array('redis', [
            config('session.driver'),
            config('cache.default'),
            config('queue.default'),
        ]);
        if ($redisInUse) {
            $checks['redis'] = $this->checkRedis();
            if ($checks['redis']['status'] !== 'ok') {
                $allHealthy = false;
            }
        } else {
            $checks['redis'] = ['status' => 'skipped', 'reason' => 'Redis is not in use'];
        }

        // ── Cache ────────────────────────
        $checks['cache'] = $this->checkCache();
        if ($checks['cache']['status'] !== 'ok') {
            $allHealthy = false;
        }

        // ── Queue ────────────────────────
        $checks['queue'] = $this->checkQueue();
        if ($checks['queue']['status'] !== 'ok') {
            $allHealthy = false;
        }

        $statusCode = $allHealthy ? 200 : 503;

        return response()->json([
            'success' => $allHealthy,
            'message' => $allHealthy ? '所有服务正常' : '部分服务异常',
            'data' => [
                'checks' => $checks,
                'app' => [
                    'name' => config('app.name'),
                    'env' => config('app.env'),
                    'debug' => config('app.debug'),
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                ],
                'timestamp' => now()->toIso8601String(),
            ],
        ], $statusCode);
    }

    // ── 检查方法 ────────────────────────────────

    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $latencyMs = round((microtime(true) - $start) * 1000, 2);
            return [
                'status' => 'ok',
                'latency_ms' => $latencyMs,
                'driver' => config('database.default'),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkRedis(): array
    {
        $start = microtime(true);
        try {
            $pong = Redis::ping();
            $latencyMs = round((microtime(true) - $start) * 1000, 2);
            return [
                'status' => 'ok',
                'latency_ms' => $latencyMs,
                'response' => $pong,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        $start = microtime(true);
        try {
            $testKey = 'health_check_' . uniqid();
            Cache::put($testKey, 'ok', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);
            $latencyMs = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => $value === 'ok' ? 'ok' : 'error',
                'latency_ms' => $latencyMs,
                'driver' => config('cache.default'),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkQueue(): array
    {
        try {
            $driver = config('queue.default');
            $connection = config("queue.connections.{$driver}");

            return [
                'status' => 'ok',
                'driver' => $driver,
                'connection' => $connection['connection'] ?? 'default',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }
}
