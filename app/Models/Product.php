<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'category_id',
        'stock',
        'price',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function getStatusAttribute(): string
    {
        if ($this->stock <= 0) {
            return 'Out of Stock';
        } elseif ($this->stock < 10) {
            return 'Low Stock';
        } else {
            return 'In Stock';
        }
    }
}
