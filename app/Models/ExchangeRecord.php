<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExchangeRecord extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'exchange_number',
        'sale_id',
        'customer_id',
        'exchange_date',
        'status',
        'reason',
        'exchanged_by',
        'store_id',
        'notes',
    ];

    protected $casts = [
        'exchange_date' => 'date',
        'sale_id' => 'integer',
        'customer_id' => 'integer',
        'exchanged_by' => 'integer',
        'store_id' => 'integer',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sale_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function exchangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exchanged_by');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
