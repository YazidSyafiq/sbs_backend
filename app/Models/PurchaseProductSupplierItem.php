<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class PurchaseProductSupplierItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'purchase_product_supplier_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
    ];

    public function purchaseProductSupplier(): BelongsTo
    {
        return $this->belongsTo(PurchaseProductSupplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function boot()
    {
        parent::boot();

        // Event: Sebelum save (untuk auto-calculate total_price)
        static::saving(function ($item) {
            $item->total_price = $item->quantity * $item->unit_price;
        });

        // Event: Setelah save (untuk update PO total dan sync ke ProductBatch)
        static::saved(function ($item) {
            // Update total amount di PO
            $item->purchaseProductSupplier->calculateTotal();

            // Sync ke ProductBatch jika ada perubahan price/quantity
            if ($item->wasChanged(['unit_price', 'quantity'])) {
                $item->syncToProductBatch();
            }
        });

        // Event: Setelah delete (untuk update PO total dan soft delete ProductBatch)
        static::deleted(function ($item) {
            // Update total amount di PO
            $item->purchaseProductSupplier->calculateTotal();

            // Soft delete ProductBatch yang terkait
            $item->deleteRelatedProductBatch();
        });

        // Event: Setelah restore (untuk restore ProductBatch juga)
        static::restored(function ($item) {
            // Update total amount di PO
            $item->purchaseProductSupplier->calculateTotal();

            // Restore ProductBatch yang terkait
            $item->restoreRelatedProductBatch();
        });
    }

    /**
     * Sync perubahan harga/quantity ke ProductBatch yang terkait
     */
    private function syncToProductBatch(): void
    {
        $pps = $this->purchaseProductSupplier;

        // Hanya sync jika PO sudah status Received atau Done (sudah ada ProductBatch)
        if (!in_array($pps->status, ['Received', 'Done'])) {
            Log::info("Item changed but PO not yet received, skip sync to ProductBatch", [
                'po_number' => $pps->po_number,
                'product_id' => $this->product_id,
                'status' => $pps->status
            ]);
            return;
        }

        // Cari ProductBatch yang terkait
        $productBatch = ProductBatch::where('purchase_product_supplier_id', $pps->id)
            ->where('product_id', $this->product_id)
            ->first();

        if ($productBatch) {
            $oldCostPrice = $productBatch->cost_price;
            $oldQuantity = $productBatch->quantity;

            // Update cost_price dan quantity di ProductBatch
            $productBatch->update([
                'cost_price' => $this->unit_price,
                'quantity' => $this->quantity,
            ]);

            Log::info("ProductBatch synced from item changes", [
                'po_number' => $pps->po_number,
                'batch_number' => $productBatch->batch_number,
                'product_id' => $this->product_id,
                'changes' => [
                    'cost_price' => ['old' => $oldCostPrice, 'new' => $this->unit_price],
                    'quantity' => ['old' => $oldQuantity, 'new' => $this->quantity],
                ]
            ]);
        } else {
            Log::warning("ProductBatch not found for item sync", [
                'po_number' => $pps->po_number,
                'product_id' => $this->product_id,
            ]);
        }
    }

    /**
     * Soft delete ProductBatch yang terkait ketika item di-delete
     */
    private function deleteRelatedProductBatch(): void
    {
        $pps = $this->purchaseProductSupplier;

        // Hanya delete jika PO sudah status Received atau Done
        if (!in_array($pps->status, ['Received', 'Done'])) {
            Log::info("Item deleted but PO not yet received, no ProductBatch to delete", [
                'po_number' => $pps->po_number,
                'product_id' => $this->product_id,
            ]);
            return;
        }

        // Cari dan soft delete ProductBatch yang terkait
        $productBatch = ProductBatch::where('purchase_product_supplier_id', $pps->id)
            ->where('product_id', $this->product_id)
            ->first();

        if ($productBatch) {
            $productBatch->delete();

            Log::info("ProductBatch soft deleted from item deletion", [
                'po_number' => $pps->po_number,
                'batch_number' => $productBatch->batch_number,
                'product_id' => $this->product_id,
            ]);
        }
    }

    /**
     * Restore ProductBatch yang terkait ketika item di-restore
     */
    private function restoreRelatedProductBatch(): void
    {
        $pps = $this->purchaseProductSupplier;

        // Hanya restore jika PO sudah status Received atau Done
        if (!in_array($pps->status, ['Received', 'Done'])) {
            Log::info("Item restored but PO not yet received, no ProductBatch to restore", [
                'po_number' => $pps->po_number,
                'product_id' => $this->product_id,
            ]);
            return;
        }

        // Cari dan restore ProductBatch yang terkait
        $productBatch = ProductBatch::withTrashed()
            ->where('purchase_product_supplier_id', $pps->id)
            ->where('product_id', $this->product_id)
            ->onlyTrashed()
            ->first();

        if ($productBatch) {
            $productBatch->restore();

            Log::info("ProductBatch restored from item restoration", [
                'po_number' => $pps->po_number,
                'batch_number' => $productBatch->batch_number,
                'product_id' => $this->product_id,
            ]);
        }
    }
}
