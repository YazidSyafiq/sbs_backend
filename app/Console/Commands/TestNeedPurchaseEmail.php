<?php

namespace App\Console\Commands;

use App\Mail\NeedPurchaseNotification;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestNeedPurchaseEmail extends Command
{
    protected $signature = 'test:need-purchase-email {email}';
    protected $description = 'Test need purchase email notification';

    public function handle()
    {
        $email = $this->argument('email');

        // Get products yang need purchase untuk testing
        $needPurchaseProducts = Product::with(['category', 'productBatches' => function ($query) {
                $query->where('quantity', '>', 0);
            }])
            ->whereHas('productBatches', function ($query) {
                $query->where('quantity', '>', 0);
            })
            ->get()
            ->filter(function ($product) {
                return $product->need_purchase > 0;
            })
            ->map(function ($product) {
                return [
                    'product' => $product,
                    'available_stock' => $product->available_stock,
                    'pending_orders' => $product->pending_orders,
                    'need_purchase' => $product->need_purchase,
                    'projected_stock' => $product->available_stock - $product->pending_orders,
                    'status' => $product->status,
                ];
            })
            ->sortBy('projected_stock')
            ->values();

        if ($needPurchaseProducts->count() > 0) {
            Mail::to($email)->send(new NeedPurchaseNotification($needPurchaseProducts));
            $this->info("🛒 Need purchase email sent to: {$email}");
            $this->info("Found {$needPurchaseProducts->count()} products needing purchase.");
            $this->info("Total units needed: {$needPurchaseProducts->sum('need_purchase')}");
        } else {
            $this->info("No products need to be purchased for testing.");
        }

        return 0;
    }
}
