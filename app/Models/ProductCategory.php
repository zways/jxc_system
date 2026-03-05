<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCategory extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'store_id',
        'name',
        'description',
        'parent_id',
        'level',
        'sort_order',
        'is_active',
    ];

    protected $attributes = ['is_active' => true];

    protected $casts = [
        'parent_id' => 'integer',
        'level' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'store_id' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
