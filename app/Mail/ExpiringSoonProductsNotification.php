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
        public Collection $expiringSoonProducts
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->expiringSoonProducts->count();
        return new Envelope(
            subject: "⏰ WARNING: {$count} Product(s) Expiring Soon",
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
