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
            // Load PurchaseProductSupplier dengan relasi yang dibutuhkan
            $purchaseProductSupplier = PurchaseProductSupplier::with([
                'items.product',
                'supplier',
                'user'
            ])->find($this->purchaseProductSupplierId);

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
                case 'Received':
                case 'Cancelled':
                case 'Done':
                    $recipients = User::whereHas('roles', function ($query) {
                        $query->whereIn('name', ['Admin', 'Supervisor', 'Manager', 'Super Admin']);
                    })->get();
                    break;

                default:
                    Log::info("No email recipients for status", [
                        'status' => $this->status,
                        'po_number' => $purchaseProductSupplier->po_number
                    ]);
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
                        'recipient' => $recipient->email,
                        'items_count' => $purchaseProductSupplier->items->count()
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed to send PO Supplier status email", [
                        'po_number' => $purchaseProductSupplier->po_number,
                        'status' => $this->status,
                        'recipient' => $recipient->email,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }

                // Kirim Firebase Notification
                $this->sendFirebaseNotification($purchaseProductSupplier, $recipient);
            }

            Log::info("PO Supplier notification job completed", [
                'po_number' => $purchaseProductSupplier->po_number,
                'status' => $this->status,
                'recipients_count' => $recipients->count(),
                'items_count' => $purchaseProductSupplier->items->count()
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to process PO Supplier status email job", [
                'purchase_product_supplier_id' => $this->purchaseProductSupplierId,
                'status' => $this->status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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

            // Prepare notification data
            $notificationData = [
                'id' => (string)$purchaseProductSupplier->id,
                'po_number' => $purchaseProductSupplier->po_number,
                'status' => $this->status,
                'type' => 'purchase_order_supplier',
                'supplier_name' => $purchaseProductSupplier->supplier->name ?? '',
                'total_amount' => (string)$purchaseProductSupplier->total_amount,
                'items_count' => (string)$purchaseProductSupplier->items->count(),
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ];

            $message = CloudMessage::withTarget('token', $deviceToken)
                ->withNotification($notification)
                ->withData($notificationData);

            $messaging->send($message);

            Log::info("Firebase notification sent", [
                'po_number' => $purchaseProductSupplier->po_number,
                'status' => $this->status,
                'user_id' => $user->id,
                'items_count' => $purchaseProductSupplier->items->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Firebase Notification Error', [
                'po_number' => $purchaseProductSupplier->po_number,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected function getNotificationTitle($status)
    {
        $titles = [
            'Requested' => 'New PO Supplier Requested',
            'Processing' => 'PO Supplier in Processing',
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
        $itemsCount = $purchaseProductSupplier->items->count();
        $totalAmount = number_format($purchaseProductSupplier->total_amount, 0, ',', '.');

        // Get first few product names for context
        $productNames = $purchaseProductSupplier->items
            ->take(2)
            ->pluck('product.name')
            ->filter()
            ->join(', ');

        if ($itemsCount > 2) {
            $productNames .= " +{$itemsCount} items";
        } elseif ($itemsCount == 1) {
            $productNames = $purchaseProductSupplier->items->first()->product->name ?? 'Product';
        }

        $bodies = [
            'Requested' => "PO {$poNumber} requested with {$itemsCount} item(s) - {$productNames}",
            'Processing' => "PO {$poNumber} is being processed - Total: Rp {$totalAmount}",
            'Received' => "PO {$poNumber} received successfully - {$itemsCount} item(s)",
            'Done' => "PO {$poNumber} completed - Total: Rp {$totalAmount}",
            'Cancelled' => "PO {$poNumber} has been cancelled"
        ];

        return $bodies[$status] ?? "PO {$poNumber} status updated to {$status}";
    }
}
