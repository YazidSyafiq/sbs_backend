<?php

namespace App\Console\Commands;

use App\Mail\ExpiredProductsNotification;
use App\Mail\ExpiringSoonProductsNotification;
use App\Models\ProductBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestExpiryEmails extends Command
{
    protected $signature = 'test:expiry-emails {email} {--type=both}';
    protected $description = 'Test expiry emails separately for batch-based system (--type=expired|expiring|both)';

    public function handle()
    {
        $email = $this->argument('email');
        $type = $this->option('type');

        if (in_array($type, ['expired', 'both'])) {
            // Get expired batches
            $expiredBatches = ProductBatch::with(['product.category'])
                ->whereNotNull('expiry_date')
                ->where('quantity', '>', 0)
                ->where('expiry_date', '<', now())
                ->orderBy('expiry_date', 'asc')
                ->get();

            if ($expiredBatches->count() > 0) {
                // Group by product
                $expiredProductsData = $expiredBatches->groupBy('product_id')->map(function ($batches, $productId) {
                    $product = $batches->first()->product;
                    return [
                        'product' => $product,
                        'batches' => $batches,
                        'total_expired_stock' => $batches->sum('quantity'),
                        'earliest_expiry' => $batches->min('expiry_date'),
                    ];
                });

                Mail::to($email)->send(new ExpiredProductsNotification($expiredProductsData));
                $this->error("🚨 Expired batches email sent to: {$email}");
                $this->error("Found {$expiredProductsData->count()} products with expired batches.");
                $this->error("Total expired batches: {$expiredBatches->count()}");
            } else {
                $this->info("No expired product batches found for testing.");
            }
        }

        if (in_array($type, ['expiring', 'both'])) {
            // Get batches expiring soon
            $expiringSoonBatches = ProductBatch::with(['product.category'])
                ->whereNotNull('expiry_date')
                ->where('quantity', '>', 0)
                ->where('expiry_date', '>=', now())
                ->where('expiry_date', '<=', now()->addDays(30))
                ->orderBy('expiry_date', 'asc')
                ->get();

            if ($expiringSoonBatches->count() > 0) {
                // Group by product
                $expiringSoonProductsData = $expiringSoonBatches->groupBy('product_id')->map(function ($batches, $productId) {
                    $product = $batches->first()->product;
                    return [
                        'product' => $product,
                        'batches' => $batches,
                        'total_expiring_stock' => $batches->sum('quantity'),
                        'earliest_expiry' => $batches->min('expiry_date'),
                    ];
                });

                Mail::to($email)->send(new ExpiringSoonProductsNotification($expiringSoonProductsData));
                $this->warn("⏰ Expiring soon batches email sent to: {$email}");
                $this->warn("Found {$expiringSoonProductsData->count()} products with expiring soon batches.");
                $this->warn("Total expiring soon batches: {$expiringSoonBatches->count()}");
            } else {
                $this->info("No expiring soon product batches found for testing.");
            }
        }

        return 0;
    }
}
