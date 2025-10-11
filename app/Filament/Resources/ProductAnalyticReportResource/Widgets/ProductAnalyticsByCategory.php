<?php

namespace App\Filament\Resources\ProductAnalyticReportResource\Widgets;

use App\Models\ProductAnalyticReport;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProductAnalyticsByCategory extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('product_analytics_filters', []);
        $stats = [];

        $categoryStats = ProductAnalyticReport::getFilteredSummaryByCategory($filters);

        // Limit to first 6 categories
        $categoryStats = $categoryStats->take(6);

        foreach ($categoryStats as $category) {
            $categoryName = $category->category_name ?: 'No Category';
            $displayName = strlen($categoryName) > 15 ? substr($categoryName, 0, 15) . '...' : $categoryName;

            $stats[] = Stat::make($displayName, number_format($category->product_count) . ' products')
                ->description('Stock: ' . number_format($category->total_stock) . ' units | Value: Rp ' . number_format($category->total_value, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-cube')
                ->color($category->stock_health_rate >= 80 ? 'success' :
                       ($category->stock_health_rate >= 60 ? 'warning' : 'danger'));
        }

        if ($categoryStats->isEmpty()) {
            $stats[] = Stat::make('No Data', 'No category data found')
                ->description('No products match the current filters')
                ->descriptionIcon('heroicon-m-information-circle')
                ->color('gray');
        }

        return $stats;
    }

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        $filters = session('product_analytics_filters', []);
        $categoryStats = ProductAnalyticReport::getFilteredSummaryByCategory($filters);

        $totalCategories = $categoryStats->count();
        $showing = min($totalCategories, 6);

        $title = 'Summary by Category';
        if ($totalCategories > 6) {
            $title .= " (Top {$showing} of {$totalCategories})";
        }

        return $title;
    }
}
