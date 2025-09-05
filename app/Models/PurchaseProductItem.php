<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseProductItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_product_id',
        'product_id',
        'quantity',
        'supplier_price',
        'unit_price',
        'total_price',
    ];

    public function purchaseProduct(): BelongsTo
    {
        return $this->belongsTo(PurchaseProduct::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Auto calculate total price
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->total_price = $item->quantity * $item->unit_price;
        });

        static::saved(function ($item) {
            $item->purchaseProduct->calculateTotal();
        });

        static::deleted(function ($item) {
            $item->purchaseProduct->calculateTotal();
        });
    }
}
