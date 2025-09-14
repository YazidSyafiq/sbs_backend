<?php

namespace App\Console\Commands;

use App\Mail\ExpiredProductsNotification;
use App\Mail\ExpiringSoonProductsNotification;
use App\Models\ProductBatch;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckProductExpiry extends Command
{
    protected $signature = 'products:check-expiry';
    protected $description = 'Check for expired and expiring soon product batches and send separate notifications';

    public function handle()
    {
        // Untuk expired batches - yang sudah expired dan masih ada stock
        $expiredBatches = ProductBatch::with(['product.category'])
            ->whereNotNull('expiry_date')
            ->where('quantity', '>', 0)
            ->where('expiry_date', '<', now())
            ->orderBy('expiry_date', 'asc')
            ->get();

        // Group expired batches by product untuk email yang lebih clean
        $expiredProductsData = $expiredBatches->groupBy('product_id')->map(function ($batches, $productId) {
            $product = $batches->first()->product;
            return [
                'product' => $product,
                'batches' => $batches,
                'total_expired_stock' => $batches->sum('quantity'),
                'earliest_expiry' => $batches->min('expiry_date'),
            ];
        });

        // Untuk expiring soon batches - yang akan expired dalam 30 hari dan masih ada stock
        $expiringSoonBatches = ProductBatch::with(['product.category'])
            ->whereNotNull('expiry_date')
            ->where('quantity', '>', 0)
            ->where('expiry_date', '>=', now())
            ->where('expiry_date', '<=', now()->addDays(30))
            ->orderBy('expiry_date', 'asc')
            ->get();

        // Group expiring soon batches by product
        $expiringSoonProductsData = $expiringSoonBatches->groupBy('product_id')->map(function ($batches, $productId) {
            $product = $batches->first()->product;
            return [
                'product' => $product,
                'batches' => $batches,
                'total_expiring_stock' => $batches->sum('quantity'),
                'earliest_expiry' => $batches->min('expiry_date'),
            ];
        });

        // Get users with Admin or Supervisor roles
        $recipients = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['Admin', 'Supervisor']);
        })->get();

        if ($recipients->count() == 0) {
            $this->warn('No recipients found with Admin or Supervisor roles.');
            return 0;
        }

        $emailsSent = 0;

        // Send expired batches notification
        if ($expiredProductsData->count() > 0) {
            foreach ($recipients as $recipient) {
                Mail::to($recipient->email)->send(
                    new ExpiredProductsNotification($expiredProductsData)
                );
                $emailsSent++;
            }

            $this->error('🚨 EXPIRED BATCHES EMAIL SENT!');
            $this->error('Recipients: ' . $recipients->count());
            $this->error('Expired products: ' . $expiredProductsData->count());
            $this->error('Total expired batches: ' . $expiredBatches->count());
            $this->line('');
        }

        // Send expiring soon batches notification
        if ($expiringSoonProductsData->count() > 0) {
            foreach ($recipients as $recipient) {
                Mail::to($recipient->email)->send(
                    new ExpiringSoonProductsNotification($expiringSoonProductsData)
                );
                $emailsSent++;
            }

            $this->warn('⏰ EXPIRING SOON BATCHES EMAIL SENT!');
            $this->warn('Recipients: ' . $recipients->count());
            $this->warn('Expiring soon products: ' . $expiringSoonProductsData->count());
            $this->warn('Total expiring soon batches: ' . $expiringSoonBatches->count());
            $this->line('');
        }

        if ($emailsSent == 0) {
            $this->info('✅ No expired or expiring product batches found. No emails sent.');
        } else {
            $this->info("📧 Total emails sent: {$emailsSent}");
        }

        return 0;
    }
}
