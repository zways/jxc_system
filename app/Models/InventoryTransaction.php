<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryTransaction extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'transaction_number',
        'product_id',
        'warehouse_id',
        'store_id',
        'transaction_type',
        'quantity',
        'unit',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'batch_number',
        'serial_number',
        'production_date',
        'expiry_date',
        'reason',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'production_date' => 'date',
        'expiry_date' => 'date',
        'product_id' => 'integer',
        'warehouse_id' => 'integer',
        'store_id' => 'integer',
        'created_by' => 'integer',
        'reference_id' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
