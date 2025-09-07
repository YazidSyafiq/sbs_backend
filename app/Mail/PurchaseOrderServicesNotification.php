<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\ServicePurchase;

class PurchaseOrderServicesNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $purchaseService;

    /**
     * Create a new message instance.
     */
    public function __construct(ServicePurchase $purchaseService)
    {
        $this->purchaseService = $purchaseService;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $statusMessages = [
            'Requested' => 'New Purchase Request Submitted',
            'Approved' => 'Purchase Order Approved',
            'In Progress' => 'Purchase Order In Progress',
            'Done' => 'Purchase Order Completed',
            'Cancelled' => 'Purchase Order Cancelled',
        ];

        $subject = $statusMessages[$this->purchaseService->status] ?? 'Purchase Order Status Update';

        return new Envelope(
            subject: $subject . ' - ' . $this->purchaseService->po_number,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.purchase-order-services-notification',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
