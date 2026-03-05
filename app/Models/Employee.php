<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'store_id',
        'employee_code',
        'name',
        'position',
        'department',
        'phone',
        'email',
        'hire_date',
        'termination_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'termination_date' => 'date',
        'store_id' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
