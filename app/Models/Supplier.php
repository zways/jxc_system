<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'store_id',
        'supplier_code',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'tax_number',
        'credit_limit',
        'outstanding_amount',
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

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function accountsPayable(): HasMany
    {
        return $this->hasMany(AccountPayable::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
