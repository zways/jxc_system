<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use BelongsToTenant;
    public $timestamps = false; // 只用 created_at

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'model_type',
        'model_id',
        'model_label',
        'store_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'description',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
        'user_id' => 'integer',
        'model_id' => 'integer',
        'store_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
