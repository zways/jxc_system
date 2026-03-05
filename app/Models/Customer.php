<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'store_id',
        'customer_code',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'tax_number',
        'credit_limit',
        'outstanding_amount',
        'customer_level',
        'payment_terms',
        'rating',
        'notes',
        'is_active',
    ];

    protected $attributes = ['is_active' => true];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
        'rating' => 'integer',
        'is_active' => 'boolean',
        'store_id' => 'integer',
    ];

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function accountsReceivable(): HasMany
    {
        return $this->hasMany(AccountReceivable::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
