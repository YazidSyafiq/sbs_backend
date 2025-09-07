<?php

namespace App\Filament\Resources\POReportServiceResource\Widgets;

use App\Models\POReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class POReportServiceProfitOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('po_service_filters', []);
        $profitStats = POReportService::getFilteredProfitOverview($filters);

        return [
            Stat::make('Realized Cost', 'Rp ' . number_format($profitStats->total_cost, 0, ',', '.'))
                ->description('Cost from paid service orders only')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make('Realized Revenue', 'Rp ' . number_format($profitStats->total_revenue, 0, ',', '.'))
                ->description('Revenue from paid service orders only')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Realized Profit', 'Rp ' . number_format($profitStats->total_profit, 0, ',', '.'))
                ->description('Profit from paid services (' . $profitStats->total_orders . ' orders)')
                ->descriptionIcon($profitStats->total_profit >= 0 ? 'heroicon-m-plus' : 'heroicon-m-minus')
                ->color($profitStats->total_profit >= 0 ? 'success' : 'danger'),

            Stat::make('Realized Margin', $profitStats->profit_margin . '%')
                ->description('Margin from paid service orders')
                ->descriptionIcon($profitStats->profit_margin >= 20 ? 'heroicon-m-trophy' :
                                ($profitStats->profit_margin >= 10 ? 'heroicon-m-star' : 'heroicon-m-exclamation-triangle'))
                ->color($profitStats->profit_margin >= 20 ? 'success' :
                       ($profitStats->profit_margin >= 10 ? 'warning' : 'danger')),

            Stat::make('Services Delivered', number_format($profitStats->total_services))
                ->description('From paid service orders only')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('info'),

            Stat::make('Potential Profit', 'Rp ' . number_format($profitStats->potential_profit, 0, ',', '.'))
                ->description('From ' . $profitStats->unpaid_orders . ' unpaid service orders')
                ->descriptionIcon('heroicon-m-clock')
                ->color($profitStats->potential_profit >= 0 ? 'warning' : 'gray'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        return 'Realized Service Profit & Loss Analysis';
    }

    public function getDescription(): ?string
    {
        return 'Profit calculations based on paid service orders only. Unpaid orders shown as potential profit.';
    }
}
