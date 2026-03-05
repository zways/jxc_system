<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'order_number',
        'customer_id',
        'order_date',
        'delivery_date',
        'subtotal',
        'discount',
        'tax_amount',
        'shipping_cost',
        'total_amount',
        'order_type',
        'status',
        'payment_status',
        'delivery_status',
        'created_by',
        'warehouse_id',
        'store_id',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'delivery_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'customer_id' => 'integer',
        'created_by' => 'integer',
        'warehouse_id' => 'integer',
        'store_id' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function accountsReceivable(): HasMany
    {
        return $this->hasMany(AccountReceivable::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }
}
