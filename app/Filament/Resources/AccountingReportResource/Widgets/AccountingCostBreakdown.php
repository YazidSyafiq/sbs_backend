<?php

namespace App\Filament\Resources\AccountingReportResource\Widgets;

use App\Models\AccountingReport;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountingCostBreakdown extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('accounting_filters', []);
        $costBreakdown = AccountingReport::getCostBreakdown($filters);

        return [
            Stat::make('Operating Expenses', 'Rp ' . number_format($costBreakdown->expense_cost, 0, ',', '.'))
                ->description($costBreakdown->expense_percentage . '% of total cost')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('red'),

            Stat::make('Product Costs', 'Rp ' . number_format($costBreakdown->product_cost, 0, ',', '.'))
                ->description($costBreakdown->product_percentage . '% of total cost')
                ->descriptionIcon('heroicon-m-cube')
                ->color('orange'),

            Stat::make('Service Costs', 'Rp ' . number_format($costBreakdown->service_cost, 0, ',', '.'))
                ->description($costBreakdown->service_percentage . '% of total cost')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('amber'),

            Stat::make('Supplier Costs', 'Rp ' . number_format($costBreakdown->supplier_cost, 0, ',', '.'))
                ->description($costBreakdown->supplier_percentage . '% of total cost')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('yellow'),
        ];
    }

    protected function getColumns(): int
    {
        return 2;
    }

    public function getHeading(): ?string
    {
        return 'Cost Breakdown by Category';
    }
}
