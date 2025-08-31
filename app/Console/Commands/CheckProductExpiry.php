<?php

namespace App\Console\Commands;

use App\Mail\ExpiredProductsNotification;
use App\Mail\ExpiringSoonProductsNotification;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckProductExpiry extends Command
{
    protected $signature = 'products:check-expiry';
    protected $description = 'Check for expired and expiring soon products and send separate notifications';

    public function handle()
    {
        // Untuk expired products
        $expiredProducts = Product::whereNotNull('expiry_date')
            ->where('stock', '>', 0)
            ->where('expiry_date', '<', now())
            ->with(['category'])
            ->get();

        // Untuk expiring soon products
        $expiringSoonProducts = Product::whereNotNull('expiry_date')
            ->where('stock', '>', 0)
            ->where('expiry_date', '>=', now())
            ->where('expiry_date', '<=', now()->addDays(30))
            ->with(['category'])
            ->get();

        // Get users with Admin or Supervisor roles
        $recipients = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['Admin', 'Supervisor']);
        })->get();

        if ($recipients->count() == 0) {
            $this->warn('No recipients found with Admin or Supervisor roles.');
            return 0;
        }

        $emailsSent = 0;

        // Send expired products notification
        if ($expiredProducts->count() > 0) {
            foreach ($recipients as $recipient) {
                Mail::to($recipient->email)->send(
                    new ExpiredProductsNotification($expiredProducts)
                );
                $emailsSent++;
            }

            $this->error('🚨 EXPIRED PRODUCTS EMAIL SENT!');
            $this->error('Recipients: ' . $recipients->count());
            $this->error('Expired products: ' . $expiredProducts->count());
            $this->line('');
        }

        // Send expiring soon products notification
        if ($expiringSoonProducts->count() > 0) {
            foreach ($recipients as $recipient) {
                Mail::to($recipient->email)->send(
                    new ExpiringSoonProductsNotification($expiringSoonProducts)
                );
                $emailsSent++;
            }

            $this->warn('⏰ EXPIRING SOON PRODUCTS EMAIL SENT!');
            $this->warn('Recipients: ' . $recipients->count());
            $this->warn('Expiring soon products: ' . $expiringSoonProducts->count());
            $this->line('');
        }

        if ($emailsSent == 0) {
            $this->info('✅ No expired or expiring products found. No emails sent.');
        } else {
            $this->info("📧 Total emails sent: {$emailsSent}");
        }

        return 0;
    }
}
