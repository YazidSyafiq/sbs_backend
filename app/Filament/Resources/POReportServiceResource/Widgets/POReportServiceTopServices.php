<?php

namespace App\Filament\Resources\POReportServiceResource\Widgets;

use App\Models\POReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class POReportServiceTopServices extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('po_service_filters', []);
        $topServices = POReportService::getTopProfitableServices($filters, 6);

        $stats = [];

        foreach ($topServices as $service) {
            $serviceName = strlen($service->service_name) > 15 ?
                          substr($service->service_name, 0, 15) . '...' :
                          $service->service_name;

            $stats[] = Stat::make($serviceName, 'Rp ' . number_format($service->total_profit, 0, ',', '.'))
                ->description('Margin: ' . $service->profit_margin . '% | Count: ' . number_format($service->total_quantity))
                ->descriptionIcon($service->profit_margin >= 20 ? 'heroicon-m-trophy' :
                                ($service->profit_margin >= 10 ? 'heroicon-m-star' : 'heroicon-m-wrench-screwdriver'))
                ->color($service->profit_margin >= 20 ? 'success' :
                       ($service->profit_margin >= 10 ? 'warning' : 'info'));
        }

        if ($topServices->isEmpty()) {
            $stats[] = Stat::make('No Data', 'No service data found')
                ->description('No services match the current filters')
                ->descriptionIcon('heroicon-m-information-circle')
                ->color('gray');
        }

        return $stats;
    }

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        return 'Most Profitable Services (From Paid Orders)';
    }

    public function getDescription(): ?string
    {
        return 'Services ranked by total profit generated from paid orders only';
    }
}
