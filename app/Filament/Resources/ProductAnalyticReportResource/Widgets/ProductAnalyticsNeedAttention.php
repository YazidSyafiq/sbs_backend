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
        $attentionItems = ProductAnalyticReport::getProductsNeedingAttention($filters, 6);

        $stats = [];

        foreach ($attentionItems as $item) {
            $stats[] = Stat::make($item->display_name, $item->status_text)
                ->description($item->detail_message)
                ->descriptionIcon($this->getAttentionIcon($item->attention_type))
                ->color($this->getAttentionColor($item->attention_type));
        }

        if ($attentionItems->isEmpty()) {
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
            'expiring_soon' => 'heroicon-m-clock',
            'out_of_stock' => 'heroicon-m-x-circle',
            'low_stock' => 'heroicon-m-exclamation-triangle',
            'need_purchase' => 'heroicon-m-shopping-cart',
            default => 'heroicon-m-information-circle'
        };
    }

    private function getAttentionColor(string $attentionType): string
    {
        return match($attentionType) {
            'expired' => 'danger',
            'expiring_soon' => 'warning',
            'out_of_stock' => 'danger',
            'low_stock' => 'warning',
            'need_purchase' => 'info',
            default => 'gray'
        };
    }

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        return 'Products Requiring Attention';
    }

    public function getDescription(): ?string
    {
        return 'Critical issues: expiry dates, stock levels, and purchase requirements';
    }
}
