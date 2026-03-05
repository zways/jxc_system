<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessAgent extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'store_id',
        'agent_code',
        'name',
        'phone',
        'email',
        'commission_rate',
        'territory',
        'status',
        'notes',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'store_id' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
