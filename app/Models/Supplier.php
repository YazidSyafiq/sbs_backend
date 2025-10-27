<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'address',
        'phone',
        'email',
        'piutang',
        'total_po'
    ];

    /**
     * Relasi ke Purchase Product Suppliers
     */
    public function purchaseProductSuppliers(): HasMany
    {
        return $this->hasMany(PurchaseProductSupplier::class);
    }

    // Auto generate supplier code
    public static function generateCode(): string
    {
        // Loop untuk mencari nomor yang belum digunakan
        $nextNumber = 1;
        $maxAttempts = 1000; // Batasi attempt untuk menghindari infinite loop

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            // Cari nomor urut terakhir dengan prefix SPR- (termasuk soft deleted)
            $lastSupplier = static::withTrashed() // Include soft deleted records
                ->where('code', 'like', 'SPR-%')
                ->orderByRaw('CAST(SUBSTRING(code, 6) AS UNSIGNED) DESC')
                ->first();

            if ($lastSupplier) {
                // Extract nomor dari code terakhir (misal: SPR-0005 -> 5)
                $lastNumber = (int) substr($lastSupplier->code, 5);
                $nextNumber = $lastNumber + 1;
            }

            // Format dengan leading zeros (0001, 0002, dst)
            $supplierCode = 'SPR-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // Cek apakah code sudah ada (termasuk soft deleted)
            $exists = static::withTrashed()
                ->where('code', $supplierCode)
                ->exists();

            if (!$exists) {
                return $supplierCode;
            }

            // Jika masih ada yang sama, increment dan coba lagi
            $nextNumber++;
        }

        // Fallback jika semua attempt gagal
        return 'SPR-' . str_pad(time() % 10000, 4, '0', STR_PAD_LEFT);
    }
}
