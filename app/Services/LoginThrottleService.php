<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * 登录失败限流服务
 *
 * 基于缓存实现的登录失败次数限制与账户锁定：
 * - 以「用户名 + IP」为维度进行计数
 * - 连续失败 5 次后锁定 15 分钟
 * - 登录成功后自动清除计数
 * - 支持查询剩余锁定时间
 */
class LoginThrottleService
{
    /** 最大允许失败次数 */
    protected int $maxAttempts = 5;

    /** 锁定时长（秒） — 15 分钟 */
    protected int $lockoutSeconds = 900;

    /**
     * 生成缓存 Key
     * 维度：用户名（或邮箱）+ 客户端 IP，防止不同来源互相影响
     */
    protected function cacheKey(string $username, string $ip): string
    {
        return 'login_throttle:' . md5($username . '|' . $ip);
    }

    /**
     * 是否已被锁定
     */
    public function isLocked(string $username, string $ip): bool
    {
        $key = $this->cacheKey($username, $ip);
        $attempts = Cache::get($key, 0);

        return $attempts >= $this->maxAttempts;
    }

    /**
     * 获取剩余锁定秒数（未锁定则返回 0）
     */
    public function remainingLockoutSeconds(string $username, string $ip): int
    {
        if (!$this->isLocked($username, $ip)) {
            return 0;
        }

        $key = $this->cacheKey($username, $ip);

        // 通过独立的锁定时间戳 Key 计算剩余秒数（兼容所有缓存驱动）
        $lockKey = $key . ':locked_at';
        $lockedAt = Cache::get($lockKey);
        if ($lockedAt) {
            $elapsed = time() - (int) $lockedAt;
            return max(0, $this->lockoutSeconds - $elapsed);
        }

        return $this->lockoutSeconds;
    }

    /**
     * 记录一次登录失败
     */
    public function recordFailedAttempt(string $username, string $ip): int
    {
        $key = $this->cacheKey($username, $ip);
        $attempts = Cache::get($key, 0) + 1;

        // 写入缓存，TTL = 锁定时长（从最后一次失败开始计算）
        Cache::put($key, $attempts, $this->lockoutSeconds);

        // 达到上限时记录锁定时间（后备 TTL 查询）
        if ($attempts >= $this->maxAttempts) {
            Cache::put($key . ':locked_at', time(), $this->lockoutSeconds);
        }

        return $attempts;
    }

    /**
     * 清除失败计数（登录成功时调用）
     */
    public function clear(string $username, string $ip): void
    {
        $key = $this->cacheKey($username, $ip);
        Cache::forget($key);
        Cache::forget($key . ':locked_at');
    }

    /**
     * 获取当前失败次数
     */
    public function attempts(string $username, string $ip): int
    {
        return Cache::get($this->cacheKey($username, $ip), 0);
    }

    /**
     * 获取剩余允许尝试次数
     */
    public function remainingAttempts(string $username, string $ip): int
    {
        return max(0, $this->maxAttempts - $this->attempts($username, $ip));
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getLockoutSeconds(): int
    {
        return $this->lockoutSeconds;
    }
}
