<?php

namespace App\Filament\Resources\ServiceAnalyticReportResource\Widgets;

use App\Models\ServiceAnalyticReport;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ServiceAnalyticsByCategory extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('service_analytics_filters', []);
        $stats = [];

        $categoryStats = ServiceAnalyticReport::getFilteredSummaryByCategory($filters);
        $categoryStats = $categoryStats->take(6);

        foreach ($categoryStats as $category) {
            $categoryName = $category->category_name ?: 'No Category';
            $displayName = strlen($categoryName) > 15 ? substr($categoryName, 0, 15) . '...' : $categoryName;

            $stats[] = Stat::make($displayName, number_format($category->service_count) . ' services')
                ->description('Total: Rp ' . number_format($category->total_value, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-tag')
                ->color('info');
        }

        if ($categoryStats->isEmpty()) {
            $stats[] = Stat::make('No Data', 'No category data found')
                ->description('No services match the current filters')
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
        $filters = session('service_analytics_filters', []);
        $categoryStats = ServiceAnalyticReport::getFilteredSummaryByCategory($filters);

        $totalCategories = $categoryStats->count();
        $showing = min($totalCategories, 6);

        $title = 'Service Summary by Category';
        if ($totalCategories > 6) {
            $title .= " (Showing top {$showing} of {$totalCategories})";
        }

        return $title;
    }
}
