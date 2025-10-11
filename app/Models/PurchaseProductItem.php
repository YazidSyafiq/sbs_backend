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
        'unit_price',
        'total_price',
        'cost_price',
        'profit_amount',
        'profit_margin',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'profit_amount' => 'decimal:2',
        'profit_margin' => 'decimal:4',
    ];

    public function purchaseProduct(): BelongsTo
    {
        return $this->belongsTo(PurchaseProduct::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate weighted average cost dari batch yang akan dikonsumsi
     */
    public function calculateWeightedAverageCost(): float
    {
        $quantityNeeded = $this->quantity;
        $remainingQuantity = $quantityNeeded;
        $totalCOGS = 0;

        // Ambil batch yang tersedia dengan urutan FIFO
        $availableBatches = ProductBatch::where('product_id', $this->product_id)
            ->where('quantity', '>', 0)
            ->orderBy('entry_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($availableBatches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $batchQuantity = $batch->quantity;
            $consumeFromBatch = min($remainingQuantity, $batchQuantity);
            $batchCOGS = $consumeFromBatch * $batch->cost_price;

            $totalCOGS += $batchCOGS;
            $remainingQuantity -= $consumeFromBatch;
        }

        return $quantityNeeded > 0 ? $totalCOGS / $quantityNeeded : 0;
    }

    /**
     * Set cost analysis data
     */
    public function setCostAnalysis(): void
    {
        $this->cost_price = $this->calculateWeightedAverageCost();
        $this->profit_amount = ($this->unit_price - $this->cost_price) * $this->quantity;
        $this->profit_margin = $this->unit_price > 0 ? (($this->unit_price - $this->cost_price) / $this->unit_price) * 100 : 0;
        $this->save();
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
