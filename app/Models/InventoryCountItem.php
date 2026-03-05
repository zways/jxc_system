<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryCountItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'inventory_count_id',
        'product_id',
        'book_quantity',
        'counted_quantity',
        'variance_quantity',
        'unit_cost',
        'variance_amount',
        'notes',
    ];

    protected $casts = [
        'inventory_count_id' => 'integer',
        'product_id' => 'integer',
        'book_quantity' => 'decimal:2',
        'counted_quantity' => 'decimal:2',
        'variance_quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'variance_amount' => 'decimal:2',
    ];

    public function inventoryCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

