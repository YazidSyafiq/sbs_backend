<?php

namespace App\Filament\Resources\AccountingReportResource\Widgets;

use App\Models\AccountingReport;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountingRevenueBreakdown extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('accounting_filters', []);
        $revenueBreakdown = AccountingReport::getRevenueBreakdown($filters);

        return [
            Stat::make('Income Revenue', 'Rp ' . number_format($revenueBreakdown->income_revenue, 0, ',', '.'))
                ->description($revenueBreakdown->income_percentage . '% of total revenue')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Product Sales Revenue', 'Rp ' . number_format($revenueBreakdown->product_revenue, 0, ',', '.'))
                ->description($revenueBreakdown->product_percentage . '% of total revenue')
                ->descriptionIcon('heroicon-m-cube')
                ->color('blue'),

            Stat::make('Service Revenue', 'Rp ' . number_format($revenueBreakdown->service_revenue, 0, ',', '.'))
                ->description($revenueBreakdown->service_percentage . '% of total revenue')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('purple'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        return 'Revenue Breakdown by Source';
    }
}
