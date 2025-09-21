<?php

namespace App\Filament\Resources\AccountingReportResource\Widgets;

use App\Models\AccountingReport;
use App\Models\ProductBatch;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountingOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('accounting_filters', []);
        $overview = AccountingReport::getAccountingOverview($filters);

        // Get current inventory value
        $currentInventoryValue = ProductBatch::where('quantity', '>', 0)
            ->selectRaw('SUM(quantity * cost_price) as total_value')
            ->first()->total_value ?? 0;

        return [
            Stat::make('Total Revenue', 'Rp ' . number_format($overview->total_revenue, 0, ',', '.'))
                ->description('Income + Product Sales + Services')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Total Cost', 'Rp ' . number_format($overview->total_cost, 0, ',', '.'))
                ->description('Expenses + Product COGS + Service Cost + Supplier Cost')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make('Profit', 'Rp ' . number_format($overview->gross_profit, 0, ',', '.'))
                ->description('Margin: ' . $overview->profit_margin . '% • Inventory: Rp ' . number_format($currentInventoryValue, 0, ',', '.'))
                ->descriptionIcon($overview->gross_profit >= 0 ? 'heroicon-m-plus' : 'heroicon-m-minus')
                ->color($overview->gross_profit >= 0 ? 'success' : 'danger'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        $filters = session('accounting_filters', []);
        return 'Financial Overview (' . AccountingReport::getPeriodLabel($filters) . ')';
    }
}
