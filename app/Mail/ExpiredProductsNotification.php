<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ExpiredProductsNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Collection $expiredProductsData
    ) {}

    public function envelope(): Envelope
    {
        $productCount = $this->expiredProductsData->count();
        $batchCount = $this->expiredProductsData->sum(function ($productData) {
            return $productData['batches']->count();
        });

        return new Envelope(
            subject: "🚨 URGENT: {$productCount} Product(s) with {$batchCount} Expired Batch(es)",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.expired-products-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
