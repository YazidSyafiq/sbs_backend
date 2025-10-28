<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

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

    /**
     * Recalculate total_po dan piutang dari semua PO yang relevan
     */
    public function recalculateTotals(): void
    {
        // Hitung total_po dari semua PO dengan status Processing, Received, Done
        $totalPo = PurchaseProductSupplier::where('supplier_id', $this->id)
            ->whereIn('status', ['Processing', 'Received', 'Done'])
            ->sum('total_amount');

        // Hitung piutang dari semua PO credit yang unpaid dengan status Processing, Received, Done
        $piutang = PurchaseProductSupplier::where('supplier_id', $this->id)
            ->where('type_po', 'credit')
            ->where('status_paid', 'unpaid')
            ->whereIn('status', ['Processing', 'Received', 'Done'])
            ->sum('total_amount');

        // Update ke database
        $this->update([
            'total_po' => $totalPo,
            'piutang' => max(0, $piutang), // Pastikan tidak negatif
        ]);

        Log::info("Supplier totals recalculated", [
            'supplier_id' => $this->id,
            'supplier_code' => $this->code,
            'supplier_name' => $this->name,
            'total_po' => $totalPo,
            'piutang' => $piutang,
        ]);
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
