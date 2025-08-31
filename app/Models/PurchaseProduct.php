<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PurchaseProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'po_number',
        'name',
        'user_id',
        'order_date',
        'expected_delivery_date',
        'status',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseProductItem::class);
    }

    public static function generatePoNumber(int $userId, string $orderDate): string
    {
        // Ambil user dan branch code
        $user = User::with('branch')->find($userId);
        $branchCode = $user->branch->code ?? 'HQ'; // Default HQ jika tidak ada branch

        // Format YYYYMM dari order date
        $yearMonth = Carbon::parse($orderDate)->format('Ym');

        // Cari nomor urut terakhir dengan format yang sama (termasuk soft deleted)
        $lastPo = static::withTrashed() // Include soft deleted records
            ->where('po_number', 'like', "PO/{$branchCode}/{$yearMonth}/%")
            ->orderByRaw('CAST(SUBSTRING_INDEX(po_number, "/", -1) AS UNSIGNED) DESC')
            ->first();

        if ($lastPo) {
            // Extract nomor dari PO number terakhir
            $lastNumber = (int) substr($lastPo->po_number, strrpos($lastPo->po_number, '/') + 1);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return "PO/{$branchCode}/{$yearMonth}/" . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    // Calculate total from items
    public function calculateTotal(): void
    {
        $this->total_amount = $this->items()->sum('total_price');
        $this->save();
    }

    public function process(): void
    {
        $this->status = 'Processing';
        $this->save();
    }

    public function cancel(): void
    {
        $this->status = 'Cancelled';
        $this->save();
    }

    // Check apakah bisa di-ship
    public function canBeShipped(): array
    {
        $insufficientItems = [];
        $validationErrors = [];

        // Cek apakah expected delivery date sudah diisi
        if (!$this->expected_delivery_date) {
            $validationErrors[] = 'Expected delivery date must be set before shipping';
        }

        // Cek stock untuk setiap item
        foreach ($this->items as $item) {
            $currentStock = $item->product->stock;
            $requiredQuantity = $item->quantity;

            if ($currentStock < $requiredQuantity) {
                $insufficientItems[] = [
                    'product_name' => $item->product->name,
                    'product_code' => $item->product->code,
                    'required' => $requiredQuantity,
                    'available' => $currentStock,
                    'shortage' => $requiredQuantity - $currentStock,
                ];
            }
        }

        return [
            'can_ship' => empty($insufficientItems) && empty($validationErrors),
            'insufficient_items' => $insufficientItems,
            'validation_errors' => $validationErrors,
        ];
    }

    public function ship(): bool
    {
        $shipCheck = $this->canBeShipped();

        if (!$shipCheck['can_ship']) {
            return false; // Return false instead of throwing exception
        }

        // Kurangi stock untuk setiap item
        foreach ($this->items as $item) {
            $item->product->stock -= $item->quantity;
            $item->product->save();
        }

        $this->status = 'Shipped';
        $this->save();

        return true;
    }

    public function received(): void
    {
        $this->status = 'Received';
        $this->save();
    }
}
