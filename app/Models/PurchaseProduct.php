<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendPurchaseOrderEmailJob;

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
        'type_po',
        'status_paid',
        'bukti_tf',
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

    /**
    * Send email notification for PO status change
    */
    private function sendStatusChangeEmail(): void
    {
        // Dispatch job untuk mengirim email
        SendPurchaseOrderEmailJob::dispatch($this->id, $this->status);

        Log::info("PO status email job dispatched", [
            'po_number' => $this->po_number,
            'status' => $this->status
        ]);
    }

    public static function generatePoNumber(int $userId, string $orderDate): string
    {
        // Ambil user dan branch code
        $user = User::with('branch')->find($userId);
        $branchCode = $user->branch->code ?? 'HQ'; // Default HQ jika tidak ada branch

        // Format YYYYMM dari order date
        $yearMonth = Carbon::parse($orderDate)->format('Ym');

        // Loop untuk mencari nomor yang belum digunakan
        $nextNumber = 1;
        $maxAttempts = 1000; // Batasi attempt untuk menghindari infinite loop

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            // Cari nomor urut terakhir dengan format yang sama (termasuk soft deleted)
            $lastPo = static::withTrashed() // Include soft deleted records
                ->where('po_number', 'like', "PO/PRD/{$branchCode}/{$yearMonth}/%")
                ->orderByRaw('CAST(SUBSTRING_INDEX(po_number, "/", -1) AS UNSIGNED) DESC')
                ->first();

            if ($lastPo) {
                // Extract nomor dari PO number terakhir
                $lastNumber = (int) substr($lastPo->po_number, strrpos($lastPo->po_number, '/') + 1);
                $nextNumber = $lastNumber + 1;
            }

            $poNumber = "PO/PRD/{$branchCode}/{$yearMonth}/" . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // Cek apakah nomor sudah ada (termasuk soft deleted)
            $exists = static::withTrashed()
                ->where('po_number', $poNumber)
                ->exists();

            if (!$exists) {
                return $poNumber;
            }

            // Jika masih ada yang sama, increment dan coba lagi
            $nextNumber++;
        }

        // Fallback jika semua attempt gagal
        return "PO/PRD/{$branchCode}/{$yearMonth}/" . str_pad(time() % 10000, 4, '0', STR_PAD_LEFT);
    }

    // Calculate total from items
    public function calculateTotal(): void
    {
        $this->total_amount = $this->items()->sum('total_price');
        $this->save();
    }

    public function canBeRequested(): array
    {
        $validationErrors = [];

        // Cek apakah ada items
        if ($this->items()->count() === 0) {
            $validationErrors[] = 'Purchase order must have at least one item';
        }

        // Cek validasi berdasarkan type_po
        if ($this->type_po === 'cash') {
            // Untuk cash purchase, harus sudah bayar dan ada bukti transfer
            if ($this->status_paid !== 'paid') {
                $validationErrors[] = 'Cash purchase requires payment status to be "Paid"';
            }

            if (empty($this->bukti_tf)) {
                $validationErrors[] = 'Cash purchase requires payment receipt upload';
            }
        }

        // Cek apakah name sudah diisi
        if (empty($this->name)) {
            $validationErrors[] = 'PO Name is required';
        }

        return [
            'can_request' => empty($validationErrors),
            'validation_errors' => $validationErrors,
        ];
    }

    public function request(): bool
    {
        $requestCheck = $this->canBeRequested();

        if (!$requestCheck['can_request']) {
            return false;
        }

        $this->status = 'Requested';
        $this->save();

        // Kirim email notification
        $this->sendStatusChangeEmail();

        return true;
    }

    /**
     * Get batches with zero cost price that will be consumed by this PO
     */
    private function getZeroCostBatches(): array
    {
        $zeroCostBatches = [];

        foreach ($this->items as $item) {
            $quantityNeeded = $item->quantity;
            $remainingQuantity = $quantityNeeded;

            // Simulate FIFO consumption
            $batches = ProductBatch::where('product_id', $item->product_id)
                ->where('quantity', '>', 0)
                ->orderBy('entry_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($batches as $batch) {
                if ($remainingQuantity <= 0) {
                    break;
                }

                $consumeFromBatch = min($remainingQuantity, $batch->quantity);

                // Check if this batch has zero cost
                if ($batch->cost_price <= 0) {
                    $zeroCostBatches[] = [
                        'product_name' => $item->product->name,
                        'product_code' => $item->product->code,
                        'batch_number' => $batch->batch_number,
                        'batch_quantity' => $batch->quantity,
                        'batch_cost_price' => $batch->cost_price,
                        'po_supplier_number' => $batch->purchaseProductSupplier?->po_number ?? 'N/A',
                        'supplier_name' => $batch->supplier?->name ?? 'Unknown',
                        'supplier_code' => $batch->supplier?->code ?? 'N/A',
                    ];
                }

                $remainingQuantity -= $consumeFromBatch;
            }
        }

        return $zeroCostBatches;
    }

    // Check apakah bisa di-process berdasarkan ProductBatch
    public function canBeProcessed(): array
    {
        $insufficientItems = [];
        $validationErrors = [];

        // Cek stock untuk setiap item berdasarkan ProductBatch
        foreach ($this->items as $item) {
            $availableStock = $item->product->available_stock; // Dari ProductBatch
            $requiredQuantity = $item->quantity;

            if ($availableStock < $requiredQuantity) {
                $insufficientItems[] = [
                    'product_name' => $item->product->name,
                    'product_code' => $item->product->code,
                    'required' => $requiredQuantity,
                    'available' => $availableStock,
                    'shortage' => $requiredQuantity - $availableStock,
                ];
            }
        }

        // Check for zero cost price batches
        $zeroCostBatches = $this->getZeroCostBatches();

        return [
            'can_process' => empty($insufficientItems) && empty($validationErrors) && empty($zeroCostBatches),
            'insufficient_items' => $insufficientItems,
            'validation_errors' => $validationErrors,
            'zero_cost_batches' => $zeroCostBatches,
        ];
    }

    public function process(): bool
    {
        $processCheck = $this->canBeProcessed();

        if (!$processCheck['can_process']) {
            return false;
        }

        // 1. Hitung dan set cost analysis untuk setiap item SEBELUM konsumsi stock
        foreach ($this->items as $item) {
            $item->setCostAnalysis();
        }

        // 2. Konsumsi stock dari ProductBatch untuk setiap item dengan FIFO
        foreach ($this->items as $item) {
            $this->consumeProductBatchFIFO($item->product_id, $item->quantity);
        }

        $this->status = 'Processing';
        $this->save();

        // Kirim email notification
        $this->sendStatusChangeEmail();

        return true;
    }

    /**
     * Konsumsi stock dari ProductBatch dengan sistem FIFO
     */
    private function consumeProductBatchFIFO(int $productId, int $quantityNeeded): void
    {
        $remainingQuantity = $quantityNeeded;

        // Ambil batch yang tersedia dengan urutan FIFO (oldest first)
        $availableBatches = ProductBatch::where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->orderBy('entry_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($availableBatches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $batchQuantity = $batch->quantity;
            $consumeFromBatch = min($remainingQuantity, $batchQuantity);

            // Update quantity di batch
            $batch->quantity -= $consumeFromBatch;
            $batch->save();

            // Kurangi sisa yang dibutuhkan
            $remainingQuantity -= $consumeFromBatch;
        }
    }

    public function cancel(): void
    {
        $this->status = 'Cancelled';
        $this->save();

        // Kirim email notification
        $this->sendStatusChangeEmail();
    }

    // Check apakah bisa di-ship (sekarang hanya check delivery date)
    public function canBeShipped(): array
    {
        $validationErrors = [];

        // Cek apakah expected delivery date sudah diisi
        if (!$this->expected_delivery_date) {
            $validationErrors[] = 'Expected delivery date must be set before shipping';
        }

        return [
            'can_ship' => empty($validationErrors),
            'validation_errors' => $validationErrors,
        ];
    }

    public function ship(): bool
    {
        $shipCheck = $this->canBeShipped();

        if (!$shipCheck['can_ship']) {
            return false;
        }

        $this->status = 'Shipped';
        $this->save();

        // Kirim email notification
        $this->sendStatusChangeEmail();

        return true;
    }

    public function received(): void
    {
        $this->status = 'Received';
        $this->save();

        // Kirim email notification
        $this->sendStatusChangeEmail();
    }

    public function canBeCompleted(): array
    {
        $validationErrors = [];

        // Cek validasi untuk credit purchase
        if ($this->type_po === 'credit') {
            // Untuk credit purchase, harus sudah bayar dan ada bukti transfer
            if ($this->status_paid !== 'paid') {
                $validationErrors[] = 'Credit purchase requires payment status to be "Paid"';
            }

            if (empty($this->bukti_tf)) {
                $validationErrors[] = 'Credit purchase requires payment receipt upload';
            }
        }

        return [
            'can_complete' => empty($validationErrors),
            'validation_errors' => $validationErrors,
        ];
    }

    public function done(): bool
    {
        $requestCheck = $this->canBeCompleted();

        if (!$requestCheck['can_complete']) {
            return false;
        }

        $this->status = 'Done';
        $this->save();

        // Kirim email notification
        $this->sendStatusChangeEmail();

        return true;
    }
}
