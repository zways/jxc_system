<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryAdjustment extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'adjustment_number',
        'product_id',
        'warehouse_id',
        'store_id',
        'quantity',
        'adjustment_type',
        'adjustment_reason',
        'adjustment_date',
        'adjusted_by',
        'status',
        'notes',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'quantity' => 'decimal:2',
        'product_id' => 'integer',
        'warehouse_id' => 'integer',
        'store_id' => 'integer',
        'adjusted_by' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
