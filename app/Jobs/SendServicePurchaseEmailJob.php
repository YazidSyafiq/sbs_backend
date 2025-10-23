<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\ServicePurchase;
use App\Models\User;
use App\Mail\PurchaseOrderServicesNotification;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class SendServicePurchaseEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $servicePurchaseId;
    protected $status;

    public function __construct($servicePurchaseId, $status)
    {
        $this->servicePurchaseId = $servicePurchaseId;
        $this->status = $status;
    }

    public function handle()
    {
        try {
            $servicePurchase = ServicePurchase::with(['user.branch', 'items.service'])
                ->find($this->servicePurchaseId);

            if (!$servicePurchase) {
                Log::error("ServicePurchase not found", [
                    'service_purchase_id' => $this->servicePurchaseId
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

                case 'Approved':
                case 'In Progress':
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
            if ($servicePurchase->user && $servicePurchase->user->branch_id) {
                $recipients = $recipients->filter(function ($user) use ($servicePurchase) {
                    // Admin, Supervisor, Manager, dan Super Admin bisa melihat semua branch
                    if ($user->hasAnyRole(['Admin', 'Supervisor', 'Manager', 'Super Admin'])) {
                        return true;
                    }

                    // User hanya yang melakukan request PO tersebut
                    if ($user->hasRole('User')) {
                        return $user->id === $servicePurchase->user_id;
                    }

                    return false;
                });
            }

            // Kirim email dan notifikasi Firebase ke setiap penerima
            foreach ($recipients as $recipient) {
                // Kirim Email
                try {
                    Mail::to($recipient->email)->send(
                        new PurchaseOrderServicesNotification($servicePurchase)
                    );

                    Log::info("Service PO status email sent", [
                        'po_number' => $servicePurchase->po_number,
                        'status' => $this->status,
                        'recipient' => $recipient->email
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed to send Service PO status email", [
                        'po_number' => $servicePurchase->po_number,
                        'status' => $this->status,
                        'recipient' => $recipient->email,
                        'error' => $e->getMessage()
                    ]);
                }

                // Kirim Firebase Notification
                $this->sendFirebaseNotification($servicePurchase, $recipient);
            }
        } catch (\Exception $e) {
            Log::error("Failed to process Service PO status email job", [
                'service_purchase_id' => $this->servicePurchaseId,
                'status' => $this->status,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function sendFirebaseNotification($servicePurchase, $user)
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
            $body = $this->getNotificationBody($servicePurchase);

            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('token', $deviceToken)
                ->withNotification($notification)
                ->withData([
                    'id' => (string)$servicePurchase->id,
                    'po_number' => $servicePurchase->po_number,
                    'status' => $this->status,
                    'type' => 'purchase_order_service',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                ]);

            $messaging->send($message);

            Log::info("Firebase notification sent", [
                'po_number' => $servicePurchase->po_number,
                'status' => $this->status,
                'user_id' => $user->id
            ]);
        } catch (\Exception $e) {
            Log::error('Firebase Notification Error', [
                'po_number' => $servicePurchase->po_number,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function getNotificationTitle($status)
    {
        $titles = [
            'Requested' => 'New Service PO',
            'Approved' => 'Service PO Approved',
            'In Progress' => 'Service PO In Progress',
            'Done' => 'Service PO Completed',
            'Cancelled' => 'Service PO Cancelled'
        ];

        return $titles[$status] ?? 'Service PO Update';
    }

    protected function getNotificationBody($servicePurchase)
    {
        $status = $this->status;
        $poNumber = $servicePurchase->po_number;

        $bodies = [
            'Requested' => "Service PO {$poNumber} has been requested and awaiting approval",
            'Approved' => "Service PO {$poNumber} has been approved",
            'In Progress' => "Service PO {$poNumber} is now in progress",
            'Done' => "Service PO {$poNumber} has been completed",
            'Cancelled' => "Service PO {$poNumber} has been cancelled"
        ];

        return $bodies[$status] ?? "Service PO {$poNumber} status updated to {$status}";
    }
}
