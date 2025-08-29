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
        if ($this->stock <= 0) {
            return 'Out of Stock';
        } elseif ($this->stock < 10) {
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

    // Method untuk generate code
    public static function generateCode($codeTemplateId): string
    {
        $codeTemplate = Code::find($codeTemplateId);
        if (!$codeTemplate) {
            return '';
        }

        // Cari nomor urut terakhir untuk code template ini
        $lastProduct = static::where('code_id', $codeTemplateId)
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
