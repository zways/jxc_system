<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesReturn extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'return_number',
        'sale_id',
        'customer_id',
        'return_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'status',
        'reason',
        'returned_by',
        'warehouse_id',
        'store_id',
        'notes',
    ];

    protected $casts = [
        'return_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'sale_id' => 'integer',
        'customer_id' => 'integer',
        'returned_by' => 'integer',
        'warehouse_id' => 'integer',
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

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
