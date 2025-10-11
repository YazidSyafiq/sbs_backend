<?php

namespace App\Filament\Resources\AccountingReportResource\Widgets;

use App\Models\AccountingReport;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountingServiceSalesAnalysis extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $filters = session('accounting_filters', []);
        $serviceSales = AccountingReport::getServiceSalesAnalysis($filters);

        $stats = [];

        if ($serviceSales->isEmpty()) {
            return [
                Stat::make('No Service Sales', 'No services were performed')
                    ->description('No service sales found in the selected period')
                    ->descriptionIcon('heroicon-m-wrench-screwdriver')
                    ->color('gray'),
            ];
        }

        // Show all services (no limit)
        foreach ($serviceSales as $service) {
            // Format profit dengan warna dan tanda
            $profitText = $service->total_profit >= 0 ? '+' : '';
            $profitText .= 'Rp ' . number_format($service->total_profit, 0, ',', '.');

            // Determine color based on profit
            $color = 'gray';
            if ($service->total_profit > 0) {
                $color = 'success';
            } elseif ($service->total_profit < 0) {
                $color = 'danger';
            }

            $stats[] = Stat::make(
                $service->service_name . ' (' . $service->service_code . ')',
                'Ordered ' . number_format($service->total_orders) . ' times • ' .
                'Performed ' . number_format($service->total_service_count) . ' services'
            )
                ->description(
                    'Revenue: Rp ' . number_format($service->total_revenue, 0, ',', '.') . ' • ' .
                    'Technician Cost: Rp ' . number_format($service->total_technician_cost, 0, ',', '.') . ' • ' .
                    'Profit: ' . $profitText . ' (' . $service->profit_margin . '%) • ' .
                    number_format($service->total_technicians_involved) . ' technicians involved'
                )
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color($color)
                ->extraAttributes([
                    'class' => 'service-stat-card',
                    'style' => 'min-height: 140px;'
                ]);
        }

        return $stats;
    }

    protected function getColumns(): int
    {
        return 2; // 2 columns for better readability
    }

    public function getHeading(): ?string
    {
        return 'Individual Service Performance';
    }

    public function getDescription(): ?string
    {
        $filters = session('accounting_filters', []);
        $serviceSales = AccountingReport::getServiceSalesAnalysis($filters);

        if ($serviceSales->isEmpty()) {
            return 'No service data available for the selected period';
        }

        $totalServices = $serviceSales->count();
        $totalRevenue = $serviceSales->sum('total_revenue');
        $totalProfit = $serviceSales->sum('total_profit');
        $totalTechnicians = $serviceSales->sum('total_technicians_involved');

        return 'Detailed analysis of ' . $totalServices . ' services • Total Revenue: Rp ' .
               number_format($totalRevenue, 0, ',', '.') . ' • Total Profit: Rp ' .
               number_format($totalProfit, 0, ',', '.') . ' • ' . $totalTechnicians . ' technicians involved';
    }
}
