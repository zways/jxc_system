<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountReceivable extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'accounts_receivable';

    protected $fillable = [
        'customer_id',
        'store_id',
        'document_type',
        'document_id',
        'document_date',
        'amount',
        'paid_amount',
        'balance',
        'due_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'document_date' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'customer_id' => 'integer',
        'store_id' => 'integer',
        'document_id' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
