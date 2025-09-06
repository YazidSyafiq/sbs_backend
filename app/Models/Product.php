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
}
