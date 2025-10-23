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
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

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

            // Kirim email dan notifikasi Firebase ke setiap penerima
            foreach ($recipients as $recipient) {
                // Kirim Email
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

                // Kirim Firebase Notification
                $this->sendFirebaseNotification($purchaseProductSupplier, $recipient);
            }
        } catch (\Exception $e) {
            Log::error("Failed to process PO Supplier status email job", [
                'purchase_product_supplier_id' => $this->purchaseProductSupplierId,
                'status' => $this->status,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function sendFirebaseNotification($purchaseProductSupplier, $user)
    {
        try {
            $messaging = app('firebase.messaging');

            // Get user's FCM token
            $deviceToken = $user->fcm_token;

            if (!$deviceToken) {
                Log::info("User has no FCM token", [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                return;
            }

            // Customize title and body based on status
            $title = $this->getNotificationTitle($this->status);
            $body = $this->getNotificationBody($purchaseProductSupplier);

            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('token', $deviceToken)
                ->withNotification($notification)
                ->withData([
                    'id' => (string)$purchaseProductSupplier->id,
                    'po_number' => $purchaseProductSupplier->po_number,
                    'status' => $this->status,
                    'type' => 'purchase_order_supplier',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                ]);

            $messaging->send($message);

            Log::info("Firebase notification sent", [
                'po_number' => $purchaseProductSupplier->po_number,
                'status' => $this->status,
                'user_id' => $user->id
            ]);
        } catch (\Exception $e) {
            Log::error('Firebase Notification Error', [
                'po_number' => $purchaseProductSupplier->po_number,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function getNotificationTitle($status)
    {
        $titles = [
            'Requested' => 'New PO Supplier',
            'Processing' => 'PO Supplier in Processing',
            'Shipped' => 'PO Supplier Has Been Shipped',
            'Received' => 'PO Supplier Received',
            'Done' => 'PO Supplier Completed',
            'Cancelled' => 'PO Supplier Cancelled'
        ];

        return $titles[$status] ?? 'PO Supplier Update';
    }

    protected function getNotificationBody($purchaseProductSupplier)
    {
        $status = $this->status;
        $poNumber = $purchaseProductSupplier->po_number;

        $bodies = [
            'Requested' => "PO Supplier {$poNumber} has been requested and awaiting approval",
            'Processing' => "PO Supplier {$poNumber} is now being processed",
            'Shipped' => "PO Supplier {$poNumber} has been shipped",
            'Received' => "PO Supplier {$poNumber} has been received",
            'Done' => "PO Supplier {$poNumber} has been completed",
            'Cancelled' => "PO Supplier {$poNumber} has been cancelled"
        ];

        return $bodies[$status] ?? "PO Supplier {$poNumber} status updated to {$status}";
    }
}
