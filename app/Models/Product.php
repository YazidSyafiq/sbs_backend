<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'category_id',
        'stock',
        'price',
        'supplier_price',
        'code_id',
        'code',
        'entry_date',
        'expiry_date'
    ];

    // PENTING: Tambahkan cast untuk dates
    protected $casts = [
        'entry_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function codeTemplate(): BelongsTo
    {
        return $this->belongsTo(Code::class, 'code_id');
    }

    public function getStatusAttribute(): string
    {
        $displayStock = $this->display_stock; // Gunakan display stock (min 0)

        if ($displayStock <= 0) {
            return 'Out of Stock';
        } elseif ($displayStock < 10) {
            return 'Low Stock';
        } else {
            return 'In Stock';
        }
    }

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

    public function getDisplayStockAttribute(): int
    {
        return max(0, $this->stock);
    }

    public function getOnRequestAttribute(): int
    {
       // Hitung total quantity dari semua PO items yang status nya Requested
        $totalRequested = PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
            $query->whereIn('status', ['Requested']);
        })
        ->where('product_id', $this->id)
        ->sum('quantity');

        return $totalRequested > 0 ? $totalRequested : 0;
    }

    public function getOnProcessingAttribute(): int
    {
       // Hitung total quantity dari semua PO items yang status nya Processing
        $totalProcessing = PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
            $query->whereIn('status', ['Processing']);
        })
        ->where('product_id', $this->id)
        ->sum('quantity');

        return $totalProcessing > 0 ? $totalProcessing : 0;
    }

    public function getOnShippedAttribute(): int
    {
       // Hitung total quantity dari semua PO items yang status nya Shipped
        $totalShipped = PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
            $query->whereIn('status', ['Shipped']);
        })
        ->where('product_id', $this->id)
        ->sum('quantity');

        return $totalShipped > 0 ? $totalShipped : 0;
    }

    public function getOnReceivedAttribute(): int
    {
       // Hitung total quantity dari semua PO items yang status nya Received
        $totalReceived = PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
            $query->whereIn('status', ['Received']);
        })
        ->where('product_id', $this->id)
        ->sum('quantity');

        return $totalReceived > 0 ? $totalReceived : 0;
    }

    public function getNeedPurchaseAttribute(): int
    {
       // Hitung total quantity dari semua PO items yang status nya Requested atau Processing
        $totalRequested = PurchaseProductItem::whereHas('purchaseProduct', function ($query) {
            $query->whereIn('status', ['Requested', 'Processing']);
        })
        ->where('product_id', $this->id)
        ->sum('quantity');

        $deficit = $totalRequested - $this->attributes['stock']; // Gunakan raw stock value

        return $deficit > 0 ? $deficit : 0;
    }

    // Accessor untuk Purchase Status
    public function getPurchaseStatusAttribute(): string
    {
        if ($this->need_purchase > 0) {
            return 'Need Purchase';
        }
        return 'Stock Available';
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
}
