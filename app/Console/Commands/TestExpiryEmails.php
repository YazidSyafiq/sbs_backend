<?php

namespace App\Console\Commands;

use App\Mail\ExpiredProductsNotification;
use App\Mail\ExpiringSoonProductsNotification;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestExpiryEmails extends Command
{
    protected $signature = 'test:expiry-emails {email} {--type=both}';
    protected $description = 'Test expiry emails separately (--type=expired|expiring|both)';

    public function handle()
    {
        $email = $this->argument('email');
        $type = $this->option('type');

        if (in_array($type, ['expired', 'both'])) {
            // Get expired products (daysUntilExpiry < 0)
            // Untuk expired products
            $expiredProducts = Product::whereNotNull('expiry_date')
                ->where('expiry_date', '<', now())
                ->with(['category'])
                ->get();

            if ($expiredProducts->count() > 0) {
                Mail::to($email)->send(new ExpiredProductsNotification($expiredProducts));
                $this->error("🚨 Expired products email sent to: {$email}");
                $this->error("Found {$expiredProducts->count()} expired products.");
            } else {
                $this->info("No expired products found for testing.");
            }
        }

        if (in_array($type, ['expiring', 'both'])) {
            // Get products expiring soon (0 <= daysUntilExpiry <= 30)
            // Untuk expiring soon products
            $expiringSoonProducts = Product::whereNotNull('expiry_date')
                ->where('expiry_date', '>=', now())
                ->where('expiry_date', '<=', now()->addDays(30))
                ->with(['category'])
                ->get();

            if ($expiringSoonProducts->count() > 0) {
                Mail::to($email)->send(new ExpiringSoonProductsNotification($expiringSoonProducts));
                $this->warn("⏰ Expiring soon products email sent to: {$email}");
                $this->warn("Found {$expiringSoonProducts->count()} expiring soon products.");
            } else {
                $this->info("No expiring soon products found for testing.");
            }
        }

        return 0;
    }
}
