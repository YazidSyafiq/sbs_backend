<?php

namespace App\Filament\Resources\POReportProductResource\Widgets;

use App\Models\POReportProduct;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class POReportProductProfitByBranch extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('po_product_filters', []);
        $branchProfits = POReportProduct::getFilteredProfitByBranch($filters);

        $stats = [];

        // Limit to first 8 branches to avoid overcrowding
        $branchProfits = $branchProfits->take(8);

        foreach ($branchProfits as $branch) {
            $branchName = $branch->branch_name ?: 'No Branch';
            $displayName = strlen($branchName) > 15 ? substr($branchName, 0, 15) . '...' : $branchName;

            $stats[] = Stat::make($displayName . ' - Realized Profit', 'Rp ' . number_format($branch->total_profit, 0, ',', '.'))
                ->description('Margin: ' . $branch->profit_margin . '% | Paid Orders: ' . number_format($branch->paid_pos))
                ->descriptionIcon($branch->profit_margin >= 20 ? 'heroicon-m-trophy' :
                                ($branch->profit_margin >= 10 ? 'heroicon-m-star' : 'heroicon-m-chart-bar'))
                ->color($branch->profit_margin >= 20 ? 'success' :
                       ($branch->profit_margin >= 10 ? 'warning' : 'info'));

            $stats[] = Stat::make($displayName . ' - Potential', 'Rp ' . number_format($branch->potential_profit, 0, ',', '.'))
                ->description('From ' . $branch->unpaid_pos . ' unpaid orders | Total Orders: ' . $branch->total_pos)
                ->descriptionIcon('heroicon-m-clock')
                ->color($branch->potential_profit >= 0 ? 'warning' : 'gray');
        }

        if ($branchProfits->isEmpty()) {
            $stats[] = Stat::make('No Data', 'No branch data found')
                ->description('No purchase orders match the current filters')
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
        $filters = session('po_product_filters', []);
        $branchProfits = POReportProduct::getFilteredProfitByBranch($filters);

        $totalBranches = $branchProfits->count();
        $showing = min($totalBranches, 8);

        $title = 'Profit Analysis by Branch';
        if ($totalBranches > 8) {
            $title .= " (Showing top {$showing} of {$totalBranches})";
        }

        return $title;
    }

    public function getDescription(): ?string
    {
        return 'Realized profit from paid orders vs potential profit from unpaid orders';
    }
}
