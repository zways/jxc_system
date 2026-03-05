<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryCount extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'count_number',
        'warehouse_id',
        'store_id',
        'type',
        'count_date',
        'counted_by',
        'status',
        'variance_amount',
        'notes',
    ];

    protected $casts = [
        'count_date' => 'date',
        'variance_amount' => 'decimal:2',
        'warehouse_id' => 'integer',
        'store_id' => 'integer',
        'counted_by' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryCountItem::class);
    }
}
