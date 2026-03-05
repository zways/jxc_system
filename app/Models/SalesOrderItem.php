<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrderItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sales_order_id',
        'product_id',
        'product_name',
        'unit',
        'quantity',
        'unit_price',
        'line_amount',
        'notes',
    ];

    protected $casts = [
        'sales_order_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_amount' => 'decimal:2',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

