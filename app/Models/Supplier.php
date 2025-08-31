<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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

    // Auto generate supplier code
    public static function generateCode(): string
    {
        $lastSupplier = static::withTrashed() // Include soft deleted records
            ->where('code', 'like', 'SUR-%')
            ->orderByRaw('CAST(SUBSTRING(code, 5) AS UNSIGNED) DESC')
            ->first();

        if ($lastSupplier) {
            // Extract nomor dari code terakhir (misal: SUR-0005 -> 5)
            $lastNumber = (int) substr($lastSupplier->code, 4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        // Format dengan leading zeros (0001, 0002, dst)
        return 'SUR-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
