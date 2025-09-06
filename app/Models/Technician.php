<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Technician extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'address',
        'phone',
        'email',
        'price',
        'piutang',
        'total_po'
    ];

    // Auto generate Technician code
    public static function generateCode(): string
    {
        // Loop untuk mencari nomor yang belum digunakan
        $nextNumber = 1;
        $maxAttempts = 1000; // Batasi attempt untuk menghindari infinite loop

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            // Cari nomor urut terakhir dengan prefix TECH- (termasuk soft deleted)
            $lastTechnician = static::withTrashed() // Include soft deleted records
                ->where('code', 'like', 'TECH-%')
                ->orderByRaw('CAST(SUBSTRING(code, 6) AS UNSIGNED) DESC')
                ->first();

            if ($lastTechnician) {
                // Extract nomor dari code terakhir (misal: TECH-0005 -> 5)
                $lastNumber = (int) substr($lastTechnician->code, 5);
                $nextNumber = $lastNumber + 1;
            }

            // Format dengan leading zeros (0001, 0002, dst)
            $TechnicianCode = 'TECH-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // Cek apakah code sudah ada (termasuk soft deleted)
            $exists = static::withTrashed()
                ->where('code', $TechnicianCode)
                ->exists();

            if (!$exists) {
                return $TechnicianCode;
            }

            // Jika masih ada yang sama, increment dan coba lagi
            $nextNumber++;
        }

        // Fallback jika semua attempt gagal
        return 'TECH-' . str_pad(time() % 10000, 4, '0', STR_PAD_LEFT);
    }
}
