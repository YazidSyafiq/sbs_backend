<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendPurchaseProductSupplierEmailJob;

class PurchaseProductSupplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'po_number',
        'name',
        'user_id',
        'supplier_id',
        'order_date',
        'received_date',
        'total_amount',
        'status',
        'type_po',
        'status_paid',
        'bukti_tf',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'received_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseProductSupplierItem::class);
    }

    public function productBatches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($purchaseProductSupplier) {
            $purchaseProductSupplier->status = 'Requested';
            $purchaseProductSupplier->save();

            if ($purchaseProductSupplier->status === 'Requested') {
                $purchaseProductSupplier->sendStatusChangeEmail();
            }
        });

        static::updating(function ($purchaseProductSupplier) {
            // Handle perubahan payment status atau bukti transfer
            if ($purchaseProductSupplier->isDirty('status_paid') ||
                $purchaseProductSupplier->isDirty('bukti_tf')) {
                $purchaseProductSupplier->handlePaymentStatusChange();
            }

            // Log perubahan total_amount saat status sudah Processing/Received/Done
            if ($purchaseProductSupplier->isDirty('total_amount') &&
                in_array($purchaseProductSupplier->status, ['Processing', 'Received', 'Done'])) {

                Log::info("PO total_amount changed, will recalculate supplier", [
                    'po_number' => $purchaseProductSupplier->po_number,
                    'old_total' => $purchaseProductSupplier->getOriginal('total_amount'),
                    'new_total' => $purchaseProductSupplier->total_amount,
                    'status' => $purchaseProductSupplier->status,
                ]);
            }
        });

        static::updated(function ($purchaseProductSupplier) {
            // Recalculate setelah total_amount berubah
            if ($purchaseProductSupplier->wasChanged('total_amount') &&
                in_array($purchaseProductSupplier->status, ['Processing', 'Received', 'Done'])) {

                if ($purchaseProductSupplier->supplier) {
                    $purchaseProductSupplier->supplier->recalculateTotals();
                }
            }
        });

        // Soft delete cascade
        static::deleting(function ($purchaseProductSupplier) {
            if (!$purchaseProductSupplier->isForceDeleting()) {
                Log::info("Soft deleting ProductBatches for PO", [
                    'po_number' => $purchaseProductSupplier->po_number,
                    'batches_count' => $purchaseProductSupplier->productBatches()->count()
                ]);

                $purchaseProductSupplier->productBatches()->delete();

                // Recalculate supplier totals setelah soft delete
                if ($purchaseProductSupplier->supplier &&
                    in_array($purchaseProductSupplier->status, ['Processing', 'Received', 'Done'])) {
                    $purchaseProductSupplier->supplier->recalculateTotals();
                }
            }
        });

        // Restore cascade
        static::restoring(function ($purchaseProductSupplier) {
            Log::info("Restoring ProductBatches for PO", [
                'po_number' => $purchaseProductSupplier->po_number,
                'trashed_batches_count' => $purchaseProductSupplier->productBatches()->onlyTrashed()->count()
            ]);

            $purchaseProductSupplier->productBatches()->onlyTrashed()->restore();
        });

        static::restored(function ($purchaseProductSupplier) {
            // Recalculate supplier totals setelah restore
            if ($purchaseProductSupplier->supplier &&
                in_array($purchaseProductSupplier->status, ['Processing', 'Received', 'Done'])) {
                $purchaseProductSupplier->supplier->recalculateTotals();
            }
        });
    }

    private function handlePaymentStatusChange(): void
    {
        if (!in_array($this->status, ['Processing', 'Received', 'Done'])) {
            return;
        }

        if ($this->type_po !== 'credit') {
            return;
        }

        $supplier = Supplier::find($this->supplier_id);
        if (!$supplier) {
            return;
        }

        $oldStatusPaid = $this->getOriginal('status_paid');
        $newStatusPaid = $this->status_paid;

        // Hanya recalculate jika memang ada perubahan payment status
        if ($oldStatusPaid !== $newStatusPaid) {
            Log::info("Payment status changed, recalculating supplier totals", [
                'po_number' => $this->po_number,
                'old_status' => $oldStatusPaid,
                'new_status' => $newStatusPaid,
                'amount' => $this->total_amount,
            ]);

            $supplier->recalculateTotals();
        }
    }

    private function sendStatusChangeEmail(): void
    {
        SendPurchaseProductSupplierEmailJob::dispatch($this->id, $this->status);

        Log::info("PO Supplier status email job dispatched", [
            'po_number' => $this->po_number,
            'status' => $this->status
        ]);
    }

    public static function generatePoNumber(int $supplierId, string $orderDate): string
    {
        $supplier = Supplier::find($supplierId);
        $supplierCode = $supplier->code ?? 'SUP';
        $yearMonth = Carbon::parse($orderDate)->format('Ym');
        $nextNumber = 1;
        $maxAttempts = 1000;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $lastPo = static::withTrashed()
                ->where('po_number', 'like', "PO/{$supplierCode}/{$yearMonth}/%")
                ->orderByRaw('CAST(SUBSTRING_INDEX(po_number, "/", -1) AS UNSIGNED) DESC')
                ->first();

            if ($lastPo) {
                $lastNumber = (int) substr($lastPo->po_number, strrpos($lastPo->po_number, '/') + 1);
                $nextNumber = $lastNumber + 1;
            }

            $poNumber = "PO/{$supplierCode}/{$yearMonth}/" . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            $exists = static::withTrashed()
                ->where('po_number', $poNumber)
                ->exists();

            if (!$exists) {
                return $poNumber;
            }

            $nextNumber++;
        }

        return "PO/{$supplierCode}/{$yearMonth}/" . str_pad(time() % 10000, 4, '0', STR_PAD_LEFT);
    }

    public function calculateTotal(): void
    {
        $this->total_amount = $this->items()->sum('total_price');
        $this->save();
    }

    public function process(): void
    {
        if ($this->type_po === 'cash') {
            if($this->status_paid === 'unpaid' || empty($this->bukti_tf)) {
                 throw new \Exception('Cash purchase must be marked as paid and payment proof uploaded before processing.');
            }
        }

        $this->status = 'Processing';
        $this->save();

        // Recalculate supplier totals
        if ($this->supplier) {
            $this->supplier->recalculateTotals();
        }

        $this->sendStatusChangeEmail();
    }

    public function cancel(): void
    {
        $this->status = 'Cancelled';
        $this->save();

        // Recalculate supplier totals
        if ($this->supplier) {
            $this->supplier->recalculateTotals();
        }

        $this->sendStatusChangeEmail();
    }

    public function receive(): void
    {
        if(!$this->received_date) {
            $this->received_date = now();
        }

        $this->status = 'Received';
        $this->save();

        // Buat ProductBatch untuk setiap item
        foreach ($this->items as $item) {
            $this->createProductBatch($item);
        }

        // Recalculate supplier totals
        if ($this->supplier) {
            $this->supplier->recalculateTotals();
        }

        $this->sendStatusChangeEmail();
    }

    private function createProductBatch($item): void
    {
        $batchNumber = ProductBatch::generateBatchNumber(
            $item->product_id,
            $this->supplier_id,
            $this->received_date->format('Y-m-d')
        );

        ProductBatch::create([
            'product_id' => $item->product_id,
            'batch_number' => $batchNumber,
            'quantity' => $item->quantity,
            'cost_price' => $item->unit_price,
            'entry_date' => $this->received_date,
            'expiry_date' => null,
            'purchase_product_supplier_id' => $this->id,
        ]);
    }

    public function done(): void
    {
        if ($this->status_paid === 'unpaid' || empty($this->bukti_tf)) {
            throw new \Exception('Payment proof must be provided before marking as Done.');
        }

        $this->status = 'Done';
        $this->save();

        // Recalculate supplier totals
        if ($this->supplier) {
            $this->supplier->recalculateTotals();
        }

        $this->sendStatusChangeEmail();
    }
}
