<?php

namespace App\Filament\Resources\ProductAnalyticReportResource\Widgets;

use App\Models\ProductAnalyticReport;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProductAnalyticsNeedAttention extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('product_analytics_filters', []);
        $products = ProductAnalyticReport::getDetailedProductsNeedAttention($filters, 6);

        $stats = [];

        foreach ($products as $product) {
            $stats[] = Stat::make($product->display_name, $product->status_text)
                ->description($product->detail_message)
                ->descriptionIcon($this->getAttentionIcon($product->attention_type))
                ->color($this->getAttentionColor($product->attention_type));
        }

        if ($products->isEmpty()) {
            $stats[] = Stat::make('All Good!', 'No products need attention')
                ->description('All products are in good condition')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success');
        }

        return $stats;
    }

    private function getAttentionIcon(string $attentionType): string
    {
        return match($attentionType) {
            'expired' => 'heroicon-m-x-circle',
            'out_of_stock' => 'heroicon-m-x-circle',
            'expiring_soon' => 'heroicon-m-clock',
            'low_stock' => 'heroicon-m-exclamation-triangle',
            default => 'heroicon-m-shopping-cart'
        };
    }

    private function getAttentionColor(string $attentionType): string
    {
        return match($attentionType) {
            'expired' => 'danger',
            'out_of_stock' => 'danger',
            'expiring_soon' => 'warning',
            'low_stock' => 'warning',
            default => 'info'
        };
    }

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        return 'Specific Products Requiring Action';
    }

    public function getDescription(): ?string
    {
        return 'Detailed list of individual products with specific attention requirements';
    }
}
