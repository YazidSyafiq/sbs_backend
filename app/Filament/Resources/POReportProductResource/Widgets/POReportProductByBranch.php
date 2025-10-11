<?php

namespace App\Filament\Resources\POReportProductResource\Widgets;

use App\Models\POReportProduct;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Auth;

class POReportProductByBranch extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('po_product_filters', []);
        $user = Auth::user();
        $isUserRole = $user && $user->hasRole('User');
        $stats = [];

        // Use POReportProduct model method
        $branchStats = POReportProduct::getFilteredAccountingSummaryByBranch($filters);

        // Limit to first 10 branches to avoid overcrowding
        $branchStats = $branchStats->take(10);

        foreach ($branchStats as $branch) {
            $branchName = $branch->branch_name ?: 'No Branch';

            // Truncate branch name if too long
            $displayName = strlen($branchName) > 15 ? substr($branchName, 0, 15) . '...' : $branchName;

            // Widget untuk total PO value
            $stats[] = Stat::make($displayName . ' - POs', number_format($branch->total_pos) . ' orders')
                ->description('Total Value: Rp ' . number_format($branch->total_po_amount, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('info');

            // Widget untuk breakdown dengan perspektif yang sesuai
            $paidReceived = $isUserRole ? 'Paid' : 'Received';
            $outstandingDebt = $isUserRole ? 'Unpaid' : 'Outstanding';

            $stats[] = Stat::make($displayName . ' - Status', $branch->payment_rate . '% paid')
                ->description($paidReceived . ': Rp ' . number_format($branch->paid_amount, 0, ',', '.') .
                           ' | ' . $outstandingDebt . ': Rp ' . number_format($branch->outstanding_debt, 0, ',', '.'))
                ->descriptionIcon($branch->payment_rate >= 80 ? 'heroicon-m-check-circle' :
                                ($branch->payment_rate >= 50 ? 'heroicon-m-clock' : 'heroicon-m-exclamation-triangle'))
                ->color($branch->payment_rate >= 80 ? 'success' :
                       ($branch->payment_rate >= 50 ? 'warning' : 'danger'));
        }

        if ($branchStats->isEmpty()) {
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
        $branchStats = POReportProduct::getFilteredAccountingSummaryByBranch($filters);

        $totalBranches = $branchStats->count();
        $showing = min($totalBranches, 10);

        $title = 'Accounting Summary by Branch';
        if ($totalBranches > 10) {
            $title .= " (Showing top {$showing} of {$totalBranches})";
        }

        return $title;
    }
}
