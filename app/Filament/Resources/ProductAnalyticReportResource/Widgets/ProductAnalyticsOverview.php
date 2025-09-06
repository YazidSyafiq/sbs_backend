<?php

namespace App\Filament\Resources\ProductAnalyticReportResource\Widgets;

use App\Models\ProductAnalyticReport;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProductAnalyticsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('product_analytics_filters', []);
        $stats = ProductAnalyticReport::getFilteredOverviewStats($filters);

        // Get contextual stats for the 6th card
        $contextStats = $this->getContextualStats($filters, $stats);

        return [
            Stat::make('Total Products', number_format($stats->total_products))
                ->description('Products in inventory')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('info'),

            Stat::make('Total Stock Units', number_format($stats->total_stock_units))
                ->description('All stock combined')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make('Total Stock Value', 'Rp ' . number_format($stats->total_stock_value, 0, ',', '.'))
                ->description('Current stock value at selling price')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Out of Stock', number_format($stats->out_of_stock))
                ->description('Products with zero stock')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($stats->out_of_stock > 0 ? 'danger' : 'success'),

            Stat::make('Low Stock', number_format($stats->low_stock))
                ->description('Products with stock < 10')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($stats->low_stock > 0 ? 'warning' : 'success'),

            // Changed from average price to products needing purchase
            Stat::make('Need Purchase', number_format($stats->products_needing_purchase))
                ->description('Products requiring purchase orders')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color($stats->products_needing_purchase > 0 ? 'warning' : 'success'),
        ];
    }

    /**
     * Get contextual statistics based on active filters
     */
    private function getContextualStats($filters, $currentStats): array
    {
        // Check what filters are active
        $hasDateFilter = !empty($filters['entry_date_from']) || !empty($filters['entry_date_until']);
        $hasStockFilter = !empty($filters['stock_status']);
        $hasExpiryFilter = !empty($filters['expiry_status']);
        $hasPriceFilter = !empty($filters['price_min']) || !empty($filters['price_max']);

        if ($hasExpiryFilter) {
            return $this->getExpiryFilterContext($currentStats);
        } elseif ($hasStockFilter) {
            return $this->getStockFilterContext($currentStats);
        } elseif ($hasDateFilter) {
            return $this->getDateFilterContext($filters, $currentStats);
        } elseif ($hasPriceFilter) {
            return $this->getPriceFilterContext($filters, $currentStats);
        } else {
            return $this->getDefaultContext($currentStats);
        }
    }

    private function getExpiryFilterContext($currentStats): array
    {
        return [
            'title' => 'Expiring Soon',
            'value' => number_format($currentStats->expiring_soon),
            'description' => 'Products expiring in 30 days',
            'icon' => 'heroicon-m-clock',
            'color' => $currentStats->expiring_soon > 0 ? 'warning' : 'success'
        ];
    }

    private function getStockFilterContext($currentStats): array
    {
        $inStockRate = $currentStats->total_products > 0 ?
            round(($currentStats->in_stock / $currentStats->total_products) * 100, 1) : 0;

        return [
            'title' => 'In Stock',
            'value' => number_format($currentStats->in_stock),
            'description' => $inStockRate . '% of products have good stock',
            'icon' => 'heroicon-m-check-circle',
            'color' => $inStockRate >= 80 ? 'success' : ($inStockRate >= 60 ? 'warning' : 'danger')
        ];
    }

    private function getDateFilterContext($filters, $currentStats): array
    {
        $dateFrom = !empty($filters['entry_date_from']) ? $filters['entry_date_from'] : null;
        $dateTo = !empty($filters['entry_date_until']) ? $filters['entry_date_until'] : null;

        if ($dateFrom && $dateTo) {
            $title = 'Selected Period';
            $description = 'Products from ' . \Carbon\Carbon::parse($dateFrom)->format('M Y') .
                          ' to ' . \Carbon\Carbon::parse($dateTo)->format('M Y');
        } elseif ($dateFrom) {
            $title = 'From ' . \Carbon\Carbon::parse($dateFrom)->format('M Y');
            $description = 'Products added since then';
        } else {
            $title = 'Until ' . \Carbon\Carbon::parse($dateTo)->format('M Y');
            $description = 'Products added until then';
        }

        return [
            'title' => $title,
            'value' => number_format($currentStats->total_products) . ' products',
            'description' => $description,
            'icon' => 'heroicon-m-calendar',
            'color' => 'info'
        ];
    }

    private function getPriceFilterContext($filters, $currentStats): array
    {
        return [
            'title' => 'Need Purchase',
            'value' => number_format($currentStats->products_needing_purchase),
            'description' => 'Products requiring purchase orders',
            'icon' => 'heroicon-m-shopping-cart',
            'color' => $currentStats->products_needing_purchase > 0 ? 'warning' : 'success'
        ];
    }

    private function getDefaultContext($currentStats): array
    {
        return [
            'title' => 'Need Purchase',
            'value' => number_format($currentStats->products_needing_purchase),
            'description' => 'Products requiring purchase orders',
            'icon' => 'heroicon-m-shopping-cart',
            'color' => $currentStats->products_needing_purchase > 0 ? 'warning' : 'success'
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        return 'Product Inventory Overview';
    }
}
