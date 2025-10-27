<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseProductSupplierItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'purchase_product_supplier_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
    ];

    public function purchaseProductSupplier(): BelongsTo
    {
        return $this->belongsTo(PurchaseProductSupplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->total_price = $item->quantity * $item->unit_price;
        });

        static::saved(function ($item) {
            $item->purchaseProductSupplier->calculateTotal();
        });

        static::deleted(function ($item) {
            $item->purchaseProductSupplier->calculateTotal();
        });
    }
}
