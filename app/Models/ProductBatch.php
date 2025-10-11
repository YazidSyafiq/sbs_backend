<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ProductBatch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'batch_number',
        'quantity',
        'cost_price',
        'entry_date',
        'expiry_date',
        'purchase_product_supplier_id'
    ];

    protected $casts = [
        'entry_date' => 'date',
        'expiry_date' => 'date',
        'quantity' => 'integer',
        'cost_price' => 'decimal:2',
    ];

    // Relationships
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseProductSupplier(): BelongsTo
    {
        return $this->belongsTo(PurchaseProductSupplier::class);
    }

    // Accessor untuk mendapatkan supplier melalui PO
    public function getSupplierAttribute()
    {
        return $this->purchaseProductSupplier?->supplier;
    }

    // Accessor untuk status expiry
    public function getExpiryStatusAttribute(): string
    {
        if (!$this->expiry_date) {
            return 'No Expiry Date';
        }

        $daysUntilExpiry = Carbon::now()->diffInDays($this->expiry_date, false);

        if ($daysUntilExpiry < 0) {
            return 'Expired';
        } elseif ($daysUntilExpiry <= 30) {
            return 'Expiring Soon';
        } else {
            return 'Fresh';
        }
    }

    // Accessor untuk stock status
    public function getStockStatusAttribute(): string
    {
        if ($this->quantity <= 0) {
            return 'Out of Stock';
        } elseif ($this->quantity < 10) {
            return 'Low Stock';
        } else {
            return 'In Stock';
        }
    }

    public static function generateBatchNumber(int $productId, int $supplierId, string $receiveDate): string
    {
        // Ambil product
        $product = Product::find($productId);
        $productCode = $product->code ?? 'PRD';

        // Ambil supplier
        $supplier = Supplier::find($supplierId);
        $supplierCode = $supplier->code ?? 'SUP';

        // Format YYYYMM dari order date
        $yearMonth = Carbon::parse($receiveDate)->format('Ym');

        // Loop untuk mencari nomor yang belum digunakan
        $nextNumber = 1;
        $maxAttempts = 1000; // Batasi attempt untuk menghindari infinite loop

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            // Cari nomor urut terakhir dengan format yang sama (termasuk soft deleted)
            $lastBatch = static::withTrashed() // Include soft deleted records
                ->where('batch_number', 'like', "{$productCode}/{$supplierCode}/{$yearMonth}/%")
                ->orderByRaw('CAST(SUBSTRING_INDEX(batch_number, "/", -1) AS UNSIGNED) DESC')
                ->first();

            if ($lastBatch) {
                // Extract nomor dari PO number terakhir
                $lastNumber = (int) substr($lastBatch->batch_number, strrpos($lastBatch->batch_number, '/') + 1);
                $nextNumber = $lastNumber + 1;
            }

            $batchNumber = "{$productCode}/{$supplierCode}/{$yearMonth}/" . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // Cek apakah nomor sudah ada (termasuk soft deleted)
            $exists = static::withTrashed()
                ->where('batch_number', $batchNumber)
                ->exists();

            if (!$exists) {
                return $batchNumber;
            }

            // Jika masih ada yang sama, increment dan coba lagi
            $nextNumber++;
        }

        // Fallback jika semua attempt gagal - gunakan timestamp untuk uniqueness
        return "{$productCode}/{$supplierCode}/{$yearMonth}/" . str_pad(time() % 10000, 4, '0', STR_PAD_LEFT);
    }
}
