<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'store_id',
        'code',
        'name',
        'description',
        'category_id',
        'barcode',
        'specification',
        'unit',
        'second_unit',
        'conversion_rate',
        'purchase_price',
        'retail_price',
        'wholesale_price',
        'min_stock',
        'max_stock',
        'track_serial',
        'track_batch',
        'is_active',
    ];

    protected $attributes = ['is_active' => true];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'min_stock' => 'decimal:2',
        'max_stock' => 'decimal:2',
        'conversion_rate' => 'decimal:2',
        'track_serial' => 'boolean',
        'track_batch' => 'boolean',
        'is_active' => 'boolean',
        'store_id' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
