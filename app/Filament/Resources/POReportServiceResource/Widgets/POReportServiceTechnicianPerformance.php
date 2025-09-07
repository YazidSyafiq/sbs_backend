<?php

namespace App\Filament\Resources\POReportServiceResource\Widgets;

use App\Models\POReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class POReportServiceTechnicianPerformance extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('po_service_filters', []);
        $technicianAnalysis = POReportService::getFilteredTechnicianAnalysis($filters);

        $stats = [];

        // Sort by total services (most active technicians) and take top 8
        $topTechnicians = $technicianAnalysis->sortByDesc('total_services')->take(8);

        foreach ($topTechnicians as $technician) {
            $technicianName = strlen($technician->technician_name) > 12 ?
                             substr($technician->technician_name, 0, 12) . '...' :
                             $technician->technician_name;

            $stats[] = Stat::make($technicianName . ' - Total Value', 'Rp ' . number_format($technician->total_po_value, 0, ',', '.'))
                ->description('Services: ' . $technician->total_services . ' | Avg: Rp ' . number_format($technician->average_service_value, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info');

            $stats[] = Stat::make($technicianName . ' - Profit', 'Rp ' . number_format($technician->realized_profit, 0, ',', '.'))
                ->description('Margin: ' . $technician->profit_margin . '% | Potential: Rp ' . number_format($technician->potential_profit, 0, ',', '.'))
                ->descriptionIcon($technician->profit_margin >= 20 ? 'heroicon-m-trophy' :
                                ($technician->profit_margin >= 10 ? 'heroicon-m-star' : 'heroicon-m-chart-bar'))
                ->color($technician->realized_profit >= 0 ? 'success' : 'danger');
        }

        if ($topTechnicians->isEmpty()) {
            $stats[] = Stat::make('No Data', 'No technician data found')
                ->description('No technicians match the current filters')
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
        $filters = session('po_service_filters', []);
        $technicianAnalysis = POReportService::getFilteredTechnicianAnalysis($filters);

        $totalTechnicians = $technicianAnalysis->count();
        $showing = min($totalTechnicians, 8);

        $title = 'Top Technician Performance & Profitability';
        if ($totalTechnicians > 8) {
            $title .= " (Showing top {$showing} of {$totalTechnicians})";
        }

        return $title;
    }

    public function getDescription(): ?string
    {
        return 'Performance metrics and profitability analysis for most active technicians';
    }
}
