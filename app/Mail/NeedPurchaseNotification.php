<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class NeedPurchaseNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Collection $needPurchaseProducts
    ) {}

    public function envelope(): Envelope
    {
        $productCount = $this->needPurchaseProducts->count();
        $totalUnitsNeeded = $this->needPurchaseProducts->sum('need_purchase');

        return new Envelope(
            subject: "🛒 PURCHASE ALERT: {$productCount} Product(s) Need Restocking ({$totalUnitsNeeded} units)",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.need-purchase-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
