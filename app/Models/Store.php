<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'store_code',
        'name',
        'manager',
        'phone',
        'contact_email',
        'address',
        'business_license',
        'type',
        'industry',
        'is_active',
        'plan',
        'max_users',
        'expires_at',
        'is_tenant',
        'parent_store_id',
        'notes',
    ];

    protected $attributes = ['is_active' => true];

    protected $casts = [
        'is_active' => 'boolean',
        'is_tenant' => 'boolean',
        'parent_store_id' => 'integer',
        'max_users' => 'integer',
        'expires_at' => 'date',
    ];

    // ─── 关联 ────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_store_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_store_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'store_id');
    }

    public function subscriptionOrders(): HasMany
    {
        return $this->hasMany(SubscriptionOrder::class);
    }

    // ─── 企业辅助方法 ─────────────────────────────────

    /**
     * 企业是否已过期
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false; // 无到期时间 = 永不过期
        }
        return $this->expires_at->isPast();
    }

    /**
     * 当前用户数是否已达上限
     */
    public function isUserLimitReached(): bool
    {
        return $this->users()->count() >= $this->max_users;
    }

    /**
     * 获取当前用户数
     */
    public function getUserCountAttribute(): int
    {
        return $this->users()->count();
    }

    /**
     * 套餐可用计划列表
     */
    public static function availablePlans(): array
    {
        return [
            'free' => ['name' => '免费版', 'max_users' => 5],
            'basic' => ['name' => '基础版', 'max_users' => 20],
            'pro' => ['name' => '专业版', 'max_users' => 50],
            'enterprise' => ['name' => '企业版', 'max_users' => 999],
        ];
    }
}
