<?php

namespace App\Filament\Resources\POReportServiceResource\Widgets;

use App\Models\POReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class POReportServiceTechnicianDebt extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('po_service_filters', []);
        $technicianAnalysis = POReportService::getFilteredTechnicianAnalysis($filters);

        $stats = [];

        // Limit to top 10 technicians with highest debt
        $topDebtTechnicians = $technicianAnalysis->filter(fn($t) => $t->actual_debt > 0)->take(10);

        foreach ($topDebtTechnicians as $technician) {
            $technicianName = strlen($technician->technician_name) > 15 ?
                             substr($technician->technician_name, 0, 15) . '...' :
                             $technician->technician_name;

            $stats[] = Stat::make($technicianName . ' - Debt', 'Rp ' . number_format($technician->actual_debt, 0, ',', '.'))
                ->description('Unpaid: Rp ' . number_format($technician->unpaid_cost, 0, ',', '.') . ' | Services: ' . $technician->unpaid_services)
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($technician->actual_debt >= 5000000 ? 'danger' : ($technician->actual_debt >= 1000000 ? 'warning' : 'info'));

            $stats[] = Stat::make($technicianName . ' - Performance', $technician->completion_rate . '%')
                ->description('Completed: ' . $technician->completed_services . '/' . $technician->total_services . ' | Paid: ' . $technician->paid_services)
                ->descriptionIcon($technician->completion_rate >= 80 ? 'heroicon-m-check-circle' : 'heroicon-m-clock')
                ->color($technician->completion_rate >= 80 ? 'success' : ($technician->completion_rate >= 60 ? 'warning' : 'danger'));
        }

        if ($topDebtTechnicians->isEmpty()) {
            $stats[] = Stat::make('No Outstanding Debt', 'All Cleared')
                ->description('No technicians have outstanding debt')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success');
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

        $totalWithDebt = $technicianAnalysis->filter(fn($t) => $t->actual_debt > 0)->count();
        $showing = min($totalWithDebt, 10);

        $title = 'Technician Outstanding Debt Analysis';
        if ($totalWithDebt > 10) {
            $title .= " (Showing top {$showing} of {$totalWithDebt})";
        }

        return $title;
    }

    public function getDescription(): ?string
    {
        return 'Technicians with outstanding debt from unpaid credit service orders';
    }
}
