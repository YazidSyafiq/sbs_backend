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
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

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

            // Kirim email dan notifikasi Firebase ke setiap penerima
            foreach ($recipients as $recipient) {
                // Kirim Email
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

                // Kirim Firebase Notification
                $this->sendFirebaseNotification($purchaseProduct, $recipient);
            }
        } catch (\Exception $e) {
            Log::error("Failed to process PO status email job", [
                'purchase_product_id' => $this->purchaseProductId,
                'status' => $this->status,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function sendFirebaseNotification($purchaseProduct, $user)
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
            $body = $this->getNotificationBody($purchaseProduct);

            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('token', $deviceToken)
                ->withNotification($notification)
                ->withData([
                    'id' => (string)$purchaseProduct->id,
                    'po_number' => $purchaseProduct->po_number,
                    'status' => $this->status,
                    'type' => 'purchase_order',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                ]);

            $messaging->send($message);

            Log::info("Firebase notification sent", [
                'po_number' => $purchaseProduct->po_number,
                'status' => $this->status,
                'user_id' => $user->id
            ]);
        } catch (\Exception $e) {
            Log::error('Firebase Notification Error', [
                'po_number' => $purchaseProduct->po_number,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function getNotificationTitle($status)
    {
        $titles = [
            'Requested' => 'New Purchase Order',
            'Processing' => 'PO in Processing',
            'Shipped' => 'PO Has Been Shipped',
            'Received' => 'PO Received',
            'Done' => 'PO Completed',
            'Cancelled' => 'PO Cancelled'
        ];

        return $titles[$status] ?? 'Purchase Order Update';
    }

    protected function getNotificationBody($purchaseProduct)
    {
        $status = $this->status;
        $poNumber = $purchaseProduct->po_number;

        $bodies = [
            'Requested' => "Purchase Order {$poNumber} has been requested and awaiting approval",
            'Processing' => "Purchase Order {$poNumber} is now being processed",
            'Shipped' => "Purchase Order {$poNumber} has been shipped",
            'Received' => "Purchase Order {$poNumber} has been received",
            'Done' => "Purchase Order {$poNumber} has been completed",
            'Cancelled' => "Purchase Order {$poNumber} has been cancelled"
        ];

        return $bodies[$status] ?? "Purchase Order {$poNumber} status updated to {$status}";
    }
}
