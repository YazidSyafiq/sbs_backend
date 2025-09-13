<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'category_id',
        'price',
        'code_id',
        'code',
        'unit',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function codeTemplate(): BelongsTo
    {
        return $this->belongsTo(Code::class, 'code_id');
    }

    public function productBatches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function availableProductBatches(): HasMany
    {
        return $this->hasMany(ProductBatch::class)->where('quantity', '>', 0);
    }

    // Get total stock dari semua batch
    public function getTotalStockAttribute(): int
    {
        return $this->productBatches()->sum('quantity');
    }

    // Get available stock (stock > 0)
    public function getAvailableStockAttribute(): int
    {
        return $this->productBatches()->where('quantity', '>', 0)->sum('quantity');
    }

    // Get average cost price dari semua batch yang tersedia
    public function getAverageCostPriceAttribute(): float
    {
        $batches = $this->productBatches()->where('quantity', '>', 0)->get();

        if ($batches->isEmpty()) {
            return 0;
        }

        $totalValue = 0;
        $totalQuantity = 0;

        foreach ($batches as $batch) {
            $totalValue += $batch->quantity * $batch->cost_price;
            $totalQuantity += $batch->quantity;
        }

        return $totalQuantity > 0 ? $totalValue / $totalQuantity : 0;
    }

    // Get pending orders (Requested & Processing status)
    public function getPendingOrdersAttribute(): int
    {
        return PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
                $query->whereIn('status', ['Requested', 'Processing']);
            })
            ->where('product_id', $this->id)
            ->sum('quantity');
    }

    // Calculate need purchase quantity (tanpa minimum stock)
    public function getNeedPurchaseAttribute(): int
    {
        $availableStock = $this->available_stock;
        $pendingOrders = $this->pending_orders;

        // Jika pending orders melebihi available stock, butuh pembelian
        if ($pendingOrders > $availableStock) {
            return $pendingOrders - $availableStock;
        }

        return 0;
    }

    // Status stock berdasarkan available stock vs pending orders
    public function getStatusAttribute(): string
    {
        $availableStock = $this->available_stock;
        $pendingOrders = $this->pending_orders;
        $projectedStock = $availableStock - $pendingOrders;

        if ($availableStock <= 0) {
            return 'Out of Stock';
        } elseif ($projectedStock <= 0) {
            return 'Critical - Orders Exceed Stock';
        } elseif ($availableStock < 10) {
            return 'Low Stock';
        } else {
            return 'In Stock';
        }
    }

    // Method untuk generate code
    public static function generateCode($codeTemplateId): string
    {
        $codeTemplate = Code::find($codeTemplateId);
        if (!$codeTemplate) {
            return '';
        }

        // Cari nomor urut terakhir untuk code template ini (termasuk soft deleted)
        $lastProduct = static::withTrashed() // Include soft deleted records
            ->where('code_id', $codeTemplateId)
            ->where('code', 'like', $codeTemplate->code . '-%')
            ->orderByRaw('CAST(SUBSTRING(code, LOCATE("-", code) + 1) AS UNSIGNED) DESC')
            ->first();

        if ($lastProduct) {
            // Extract nomor dari code terakhir (misal: SYR-005 -> 5)
            $lastNumber = (int) substr($lastProduct->code, strlen($codeTemplate->code) + 1);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        // Format dengan leading zeros (001, 002, dst)
        return $codeTemplate->code . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Update batch numbers ketika product code berubah
     */
    public function updateProductBatchNumbers(): void
    {
        $batches = $this->productBatches()->get();

        foreach ($batches as $batch) {
            $oldBatchNumber = $batch->batch_number;

            // Parse batch number lama: PRODUCTCODE/SUPPLIERCODE/YYYYMM/NNNN
            $parts = explode('/', $oldBatchNumber);

            if (count($parts) === 4) {
                // Ganti bagian product code dengan code yang baru
                $parts[0] = $this->code;

                // Rebuild batch number
                $newBatchNumber = implode('/', $parts);

                // Update batch number
                $batch->update([
                    'batch_number' => $newBatchNumber
                ]);
            }
        }
    }
}
