<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialTransaction extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'transaction_number',
        'transaction_date',
        'type',
        'category',
        'amount',
        'currency',
        'related_model_id',
        'related_model_type',
        'created_by',
        'store_id',
        'status',
        'description',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'created_by' => 'integer',
        'store_id' => 'integer',
        'related_model_id' => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * 获取关联的模型实例（多态关系）
     */
    public function relatedModel()
    {
        return $this->morphTo();
    }
}
