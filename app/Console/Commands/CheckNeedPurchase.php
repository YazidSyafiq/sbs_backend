<?php

namespace App\Console\Commands;

use App\Mail\NeedPurchaseNotification;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckNeedPurchase extends Command
{
    protected $signature = 'products:check-need-purchase';
    protected $description = 'Check for products that need to be purchased and send notifications';

    public function handle()
    {
        // Get products yang need purchase (available stock < pending orders)
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
            ->sortBy('projected_stock') // Sort by most critical first
            ->values();

        // Get users with Admin or Supervisor roles
        $recipients = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['Admin', 'Supervisor', 'Super Admin']);
        })->get();

        if ($recipients->count() == 0) {
            $this->warn('No recipients found with Admin or Supervisor roles.');
            return 0;
        }

        $emailsSent = 0;

        if ($needPurchaseProducts->count() > 0) {
            foreach ($recipients as $recipient) {
                Mail::to($recipient->email)->send(
                    new NeedPurchaseNotification($needPurchaseProducts)
                );
                $emailsSent++;
            }

            $this->info('🛒 NEED PURCHASE EMAIL SENT!');
            $this->info('Recipients: ' . $recipients->count());
            $this->info('Products needing purchase: ' . $needPurchaseProducts->count());
            $this->info('Total units needed: ' . $needPurchaseProducts->sum('need_purchase'));
            $this->line('');
        } else {
            $this->info('✅ No products need to be purchased. All stock levels are adequate.');
        }

        if ($emailsSent > 0) {
            $this->info("📧 Total emails sent: {$emailsSent}");
        }

        return 0;
    }
}
