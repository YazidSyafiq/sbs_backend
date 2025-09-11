<?php

namespace App\Filament\Resources\AccountingReportResource\Widgets;

use App\Models\AccountingReport;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountingServiceSummary extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('accounting_filters', []);
        $serviceSales = AccountingReport::getServiceSalesAnalysis($filters);

        if ($serviceSales->isEmpty()) {
            return [
                Stat::make('No Service Data', 'No service sales found')
                    ->description('No services performed in the selected period')
                    ->descriptionIcon('heroicon-m-wrench-screwdriver')
                    ->color('gray'),
            ];
        }

        $totalServices = $serviceSales->count();
        $totalOrders = $serviceSales->sum('total_orders');
        $totalServiceCount = $serviceSales->sum('total_service_count');
        $totalRevenue = $serviceSales->sum('total_revenue');
        $totalTechnicianCost = $serviceSales->sum('total_technician_cost');
        $totalProfit = $serviceSales->sum('total_profit');
        $avgProfitMargin = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0;
        $totalTechniciansInvolved = $serviceSales->sum('total_technicians_involved');

        // Find best and worst performing services
        $bestService = $serviceSales->sortByDesc('total_profit')->first();
        $worstService = $serviceSales->sortBy('total_profit')->first();

        return [
            Stat::make('Total Services Performance', $totalServices . ' different services')
                ->description('Across ' . number_format($totalOrders) . ' orders • ' . number_format($totalServiceCount) . ' services performed • ' . $totalTechniciansInvolved . ' technicians')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('blue'),

            Stat::make('Services Revenue Summary', 'Rp ' . number_format($totalRevenue, 0, ',', '.'))
                ->description('Technician Cost: Rp ' . number_format($totalTechnicianCost, 0, ',', '.') . ' • Profit: Rp ' . number_format($totalProfit, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color($totalProfit > 0 ? 'success' : ($totalProfit < 0 ? 'danger' : 'gray')),

            Stat::make('Services Profit Analysis', $avgProfitMargin . '% avg margin')
                ->description('Best: ' . ($bestService ? $bestService->service_name . ' (' . $bestService->profit_margin . '%)' : 'N/A'))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($avgProfitMargin > 0 ? 'success' : ($avgProfitMargin < 0 ? 'danger' : 'gray')),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        return 'Services Performance Summary';
    }

    public function getDescription(): ?string
    {
        return 'Overview of all service performance metrics';
    }
}
