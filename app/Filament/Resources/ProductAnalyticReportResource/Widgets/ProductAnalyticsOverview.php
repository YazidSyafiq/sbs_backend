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

        return [
            Stat::make('Total Products', number_format($stats->total_products))
                ->description('Products in analytics')
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

            Stat::make('Need Purchase', number_format($stats->products_needing_purchase))
                ->description('Products requiring purchase orders')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color($stats->products_needing_purchase > 0 ? 'warning' : 'success'),
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
