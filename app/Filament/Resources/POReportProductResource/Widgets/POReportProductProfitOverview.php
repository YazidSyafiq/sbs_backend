<?php

namespace App\Filament\Resources\POReportProductResource\Widgets;

use App\Models\POReportProduct;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class POReportProductProfitOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('po_product_filters', []);
        $profitStats = POReportProduct::getFilteredProfitOverview($filters);

        return [
            Stat::make('Realized Cost', 'Rp ' . number_format($profitStats->total_cost, 0, ',', '.'))
                ->description('Cost from paid orders only')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make('Realized Revenue', 'Rp ' . number_format($profitStats->total_revenue, 0, ',', '.'))
                ->description('Revenue from paid orders only')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Realized Profit', 'Rp ' . number_format($profitStats->total_profit, 0, ',', '.'))
                ->description('Profit from paid orders (' . $profitStats->total_orders . ' orders)')
                ->descriptionIcon($profitStats->total_profit >= 0 ? 'heroicon-m-plus' : 'heroicon-m-minus')
                ->color($profitStats->total_profit >= 0 ? 'success' : 'danger'),

            Stat::make('Realized Margin', $profitStats->profit_margin . '%')
                ->description('Margin from paid orders')
                ->descriptionIcon($profitStats->profit_margin >= 20 ? 'heroicon-m-trophy' :
                                ($profitStats->profit_margin >= 10 ? 'heroicon-m-star' : 'heroicon-m-exclamation-triangle'))
                ->color($profitStats->profit_margin >= 20 ? 'success' :
                       ($profitStats->profit_margin >= 10 ? 'warning' : 'danger')),

            Stat::make('Items Sold', number_format($profitStats->total_items))
                ->description('From paid orders only')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info'),

            Stat::make('Potential Profit', 'Rp ' . number_format($profitStats->potential_profit, 0, ',', '.'))
                ->description('From ' . $profitStats->unpaid_orders . ' unpaid orders')
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
        return 'Realized Profit & Loss Analysis';
    }

    public function getDescription(): ?string
    {
        return 'Profit calculations based on paid orders only. Unpaid orders shown as potential profit.';
    }
}
