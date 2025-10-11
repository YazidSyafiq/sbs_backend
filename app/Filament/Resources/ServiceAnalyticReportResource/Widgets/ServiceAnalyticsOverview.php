<?php

namespace App\Filament\Resources\ServiceAnalyticReportResource\Widgets;

use App\Models\ServiceAnalyticReport;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ServiceAnalyticsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('service_analytics_filters', []);
        $stats = ServiceAnalyticReport::getFilteredOverviewStats($filters);

        return [
            Stat::make('Total Services', number_format($stats->total_services))
                ->description('Services available')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('info'),

            Stat::make('Total Service Value', 'Rp ' . number_format($stats->total_service_value, 0, ',', '.'))
                ->description('All combined value')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Highest Price', 'Rp ' . number_format($stats->highest_price, 0, ',', '.'))
                ->description('Most expensive service')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Lowest Price', 'Rp ' . number_format($stats->lowest_price, 0, ',', '.'))
                ->description('Most affordable service')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('warning'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }

    public function getHeading(): ?string
    {
        return 'Service Overview';
    }
}
