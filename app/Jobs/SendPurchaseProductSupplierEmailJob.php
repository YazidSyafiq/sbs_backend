<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseProductSupplier;
use App\Models\User;
use App\Mail\PurchaseOrderSupplierProductsNotification;

class SendPurchaseProductSupplierEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $purchaseProductSupplierId;
    protected $status;

    public function __construct($purchaseProductSupplierId, $status)
    {
        $this->purchaseProductSupplierId = $purchaseProductSupplierId;
        $this->status = $status;
    }

    public function handle()
    {
        try {
            $purchaseProductSupplier = PurchaseProductSupplier::find($this->purchaseProductSupplierId);

            if (!$purchaseProductSupplier) {
                Log::error("PurchaseProductSupplier not found", [
                    'purchase_product_supplier_id' => $this->purchaseProductSupplierId
                ]);
                return;
            }

            $recipients = [];

            // Tentukan penerima email berdasarkan status PO
            switch ($this->status) {
                case 'Requested':
                case 'Processing':
                case 'Shipped':
                case 'Received':
                case 'Cancelled':
                case 'Done':
                    $recipients = User::whereHas('roles', function ($query) {
                        $query->whereIn('name', ['Admin', 'Supervisor', 'Manager', 'Super Admin']);
                    })->get();
                    break;

                default:
                    return;
            }

            // Kirim email ke setiap penerima
            foreach ($recipients as $recipient) {
                try {
                    Mail::to($recipient->email)->send(
                        new PurchaseOrderSupplierProductsNotification($purchaseProductSupplier)
                    );

                    Log::info("PO Supplier status email sent", [
                        'po_number' => $purchaseProductSupplier->po_number,
                        'status' => $this->status,
                        'recipient' => $recipient->email
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed to send PO Supplier status email", [
                        'po_number' => $purchaseProductSupplier->po_number,
                        'status' => $this->status,
                        'recipient' => $recipient->email,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to process PO Supplier status email job", [
                'purchase_product_supplier_id' => $this->purchaseProductSupplierId,
                'status' => $this->status,
                'error' => $e->getMessage()
            ]);
        }
    }
}
