<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseProduct;
use App\Models\User;
use App\Mail\PurchaseOrderProductsNotification;

class SendPurchaseOrderEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $purchaseProductId;
    protected $status;

    public function __construct($purchaseProductId, $status)
    {
        $this->purchaseProductId = $purchaseProductId;
        $this->status = $status;
    }

    public function handle()
    {
        try {
            $purchaseProduct = PurchaseProduct::with(['user.branch', 'items.product'])
                ->find($this->purchaseProductId);

            if (!$purchaseProduct) {
                Log::error("PurchaseProduct not found", [
                    'purchase_product_id' => $this->purchaseProductId
                ]);
                return;
            }

            $recipients = [];

            // Tentukan penerima email berdasarkan status PO
            switch ($this->status) {
                case 'Requested':
                    $recipients = User::whereHas('roles', function ($query) {
                        $query->whereIn('name', ['User', 'Admin', 'Supervisor', 'Manager', 'Super Admin']);
                    })->get();
                    break;

                case 'Processing':
                case 'Shipped':
                case 'Received':
                case 'Cancelled':
                    $recipients = User::whereHas('roles', function ($query) {
                        $query->whereIn('name', ['User', 'Admin', 'Supervisor']);
                    })->get();
                    break;

                case 'Done':
                    $recipients = User::whereHas('roles', function ($query) {
                        $query->whereIn('name', ['User', 'Admin', 'Supervisor', 'Manager', 'Super Admin']);
                    })->get();
                    break;

                default:
                    return;
            }

            // Filter recipients berdasarkan role dan branch
            if ($purchaseProduct->user && $purchaseProduct->user->branch_id) {
                $recipients = $recipients->filter(function ($user) use ($purchaseProduct) {
                    if ($user->hasAnyRole(['Admin', 'Supervisor', 'Manager', 'Super Admin'])) {
                        return true;
                    }

                    if ($user->hasRole('User')) {
                        return $user->id === $purchaseProduct->user_id;
                    }

                    return false;
                });
            }

            // Kirim email ke setiap penerima
            foreach ($recipients as $recipient) {
                try {
                    Mail::to($recipient->email)->send(
                        new PurchaseOrderProductsNotification($purchaseProduct)
                    );

                    Log::info("PO status email sent", [
                        'po_number' => $purchaseProduct->po_number,
                        'status' => $this->status,
                        'recipient' => $recipient->email
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed to send PO status email", [
                        'po_number' => $purchaseProduct->po_number,
                        'status' => $this->status,
                        'recipient' => $recipient->email,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to process PO status email job", [
                'purchase_product_id' => $this->purchaseProductId,
                'status' => $this->status,
                'error' => $e->getMessage()
            ]);
        }
    }
}
