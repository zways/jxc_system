<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionOrder extends Model
{
    protected $fillable = [
        'store_id',
        'out_trade_no',
        'plan',
        'period',
        'amount',
        'currency',
        'channel',
        'status',
        'paid_at',
        'channel_trade_no',
        'raw_notify',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'raw_notify' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public static function generateOutTradeNo(): string
    {
        return 'SUB' . date('YmdHis') . strtoupper(substr(uniqid(), -6));
    }
}
