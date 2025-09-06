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

        // Use ProductAnalyticReport model method
        $categoryStats = ProductAnalyticReport::getFilteredSummaryByCategory($filters);

        // Limit to first 10 categories to avoid overcrowding
        $categoryStats = $categoryStats->take(10);

        foreach ($categoryStats as $category) {
            $categoryName = $category->category_name ?: 'No Category';

            // Truncate category name if too long
            $displayName = strlen($categoryName) > 15 ? substr($categoryName, 0, 15) . '...' : $categoryName;

            // Widget untuk total products and stock
            $stats[] = Stat::make($displayName . ' - Products', number_format($category->product_count) . ' items')
                ->description('Stock: ' . number_format($category->total_stock) . ' units')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info');

            // Widget untuk stock health
            $stats[] = Stat::make($displayName . ' - Health', $category->stock_health_rate . '% healthy')
                ->description('Out: ' . $category->out_of_stock_count . ' | Low: ' . $category->low_stock_count)
                ->descriptionIcon($category->stock_health_rate >= 80 ? 'heroicon-m-check-circle' :
                                ($category->stock_health_rate >= 60 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-x-circle'))
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
        return 2;
    }

    public function getHeading(): ?string
    {
        $filters = session('product_analytics_filters', []);
        $categoryStats = ProductAnalyticReport::getFilteredSummaryByCategory($filters);

        $totalCategories = $categoryStats->count();
        $showing = min($totalCategories, 10);

        $title = 'Product Summary by Category';
        if ($totalCategories > 10) {
            $title .= " (Showing top {$showing} of {$totalCategories})";
        }

        return $title;
    }
}
