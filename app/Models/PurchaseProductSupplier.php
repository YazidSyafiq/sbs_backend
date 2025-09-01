<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PurchaseProductSupplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'po_number',
        'name',
        'product_id',
        'user_id',
        'supplier_id',
        'quantity',
        'unit_price',
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
        'quantity' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function generatePoNumber(int $supplierId, string $orderDate): string
    {
        // Ambil supplier
        $supplier = Supplier::find($supplierId);
        $supplierCode = $supplier->code ?? 'SUP'; // Default SUP jika tidak ada code

        // Format YYYYMM dari order date
        $yearMonth = Carbon::parse($orderDate)->format('Ym');

        // Cari nomor urut terakhir dengan format yang sama (termasuk soft deleted)
        $lastPo = static::withTrashed() // Include soft deleted records
            ->where('po_number', 'like', "PO/{$supplierCode}/{$yearMonth}/%")
            ->orderByRaw('CAST(SUBSTRING_INDEX(po_number, "/", -1) AS UNSIGNED) DESC')
            ->first();

        if ($lastPo) {
            // Extract nomor dari PO number terakhir
            $lastNumber = (int) substr($lastPo->po_number, strrpos($lastPo->po_number, '/') + 1);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return "PO/{$supplierCode}/{$yearMonth}/" . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    // Calculate total
    public function calculateTotal(): void
    {
        $this->total_amount = $this->quantity * $this->product->supplier_price;
        $this->save();
    }

    public function process(): void
    {
        if ($this->type_po === 'cash') {
            if($this->status_paid === 'unpaid' || empty($this->bukti_tf)) {
                 throw new \Exception('Cash purchase must be marked as paid and payment proof uploaded before processing.');
            }
        }

        if ($this->supplier) {
            $supplier = Supplier::where('id', $this->supplier_id)->first();

            $supplier->update([
                'total_po' => $supplier->total_po + $this->total_amount,
            ]);

            if ($this->type_po === 'credit') {
                $supplier->update([
                    'piutang' => $supplier->piutang + $this->total_amount,
                ]);
            }
        }

        $this->status = 'Processing';
        $this->save();
    }

    public function cancel(): void
    {
        // Rollback supplier total jika sudah diproses
        if ($this->status === 'Processing' && $this->supplier) {
            $supplier = Supplier::where('id', $this->supplier_id)->first();

            $supplier->update([
                'total_po' => $supplier->total_po - $this->total_amount,
            ]);

            if($this->type_po == 'credit'){
                $supplier->update([
                    'piutang' => $supplier->piutang - $this->total_amount,
                ]);
            }
        }

        $this->status = 'Cancelled';
        $this->save();
    }

    public function receive(): void
    {
        if(!$this->received_date) {
            $this->received_date = now();
        }

        $product = Product::where('id', $this->product_id)->first();
        $product->update([
            'stock' => $product->stock + $this->quantity,
        ]);

        $this->status = 'Received';
        $this->save();
    }

    public function done(): void
    {
        // Validasi: harus ada bukti transfer jika masih unpaid
        if ($this->status_paid === 'unpaid' || empty($this->bukti_tf)) {
            throw new \Exception('Payment proof must be provided before marking as Done.');
        }

        if ($this->supplier) {
            $supplier = Supplier::where('id', $this->supplier_id)->first();

            // Jika tipe PO kredit, maka kurangi piutang
            if ($this->type_po === 'credit') {
                $supplier->update([
                    'piutang' => $supplier->piutang - $this->total_amount,
                ]);
            }
        }

        $this->status = 'Done';
        $this->save();
    }


}
