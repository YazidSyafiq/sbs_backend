<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\PurchaseProduct;

class PurchaseOrderProductsNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $purchaseProduct;

    /**
     * Create a new message instance.
     */
    public function __construct(PurchaseProduct $purchaseProduct)
    {
        $this->purchaseProduct = $purchaseProduct;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $statusMessages = [
            'Requested' => 'New Purchase Request Submitted',
            'Processing' => 'Purchase Order Being Processed',
            'Shipped' => 'Purchase Order Shipped',
            'Received' => 'Purchase Order Received',
            'Done' => 'Purchase Order Completed',
            'Cancelled' => 'Purchase Order Cancelled',
        ];

        $subject = $statusMessages[$this->purchaseProduct->status] ?? 'Purchase Order Status Update';

        return new Envelope(
            subject: $subject . ' - ' . $this->purchaseProduct->po_number,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.purchase-order-products-notification',
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
