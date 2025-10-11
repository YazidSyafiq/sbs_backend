<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ExpiringSoonProductsNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Collection $expiringSoonProductsData
    ) {}

    public function envelope(): Envelope
    {
        $productCount = $this->expiringSoonProductsData->count();
        $batchCount = $this->expiringSoonProductsData->sum(function ($productData) {
            return $productData['batches']->count();
        });

        return new Envelope(
            subject: "⏰ WARNING: {$productCount} Product(s) with {$batchCount} Expiring Batch(es)",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.expiring-soon-products-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
