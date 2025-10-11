<?php

namespace App\Filament\Resources\POReportServiceResource\Widgets;

use App\Models\POReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class POReportServiceTechnicianOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('po_service_filters', []);
        $technicianDebtStats = POReportService::getTechnicianDebtOverview($filters);

        return [
            Stat::make('Total Debt to Technicians', 'Rp ' . number_format($technicianDebtStats->total_debt_to_technicians, 0, ',', '.'))
                ->description('Outstanding payments to technicians')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($technicianDebtStats->total_debt_to_technicians > 0 ? 'danger' : 'success'),

            Stat::make('Active Technicians', number_format($technicianDebtStats->total_technicians))
                ->description($technicianDebtStats->technicians_with_debt . ' technicians have outstanding debt')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            Stat::make('Average Debt per Technician', 'Rp ' . number_format($technicianDebtStats->average_debt_per_technician, 0, ',', '.'))
                ->description('Average outstanding amount per technician')
                ->descriptionIcon('heroicon-m-calculator')
                ->color($technicianDebtStats->average_debt_per_technician > 0 ? 'warning' : 'success'),

            Stat::make('Debt Percentage', $technicianDebtStats->debt_percentage . '%')
                ->description('Percentage of total cost owed to technicians')
                ->descriptionIcon($technicianDebtStats->debt_percentage >= 50 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-chart-pie')
                ->color($technicianDebtStats->debt_percentage >= 50 ? 'danger' : ($technicianDebtStats->debt_percentage >= 25 ? 'warning' : 'success')),

            Stat::make('Service Completion Rate', $technicianDebtStats->completion_rate . '%')
                ->description($technicianDebtStats->completed_services . ' of ' . $technicianDebtStats->total_services . ' services completed')
                ->descriptionIcon($technicianDebtStats->completion_rate >= 80 ? 'heroicon-m-check-circle' : 'heroicon-m-clock')
                ->color($technicianDebtStats->completion_rate >= 80 ? 'success' : ($technicianDebtStats->completion_rate >= 60 ? 'warning' : 'danger')),

            Stat::make('Profit Realization Rate', $technicianDebtStats->profit_realization_rate . '%')
                ->description('Percentage of profit realized from payments')
                ->descriptionIcon($technicianDebtStats->profit_realization_rate >= 70 ? 'heroicon-m-banknotes' : 'heroicon-m-clock')
                ->color($technicianDebtStats->profit_realization_rate >= 70 ? 'success' : ($technicianDebtStats->profit_realization_rate >= 50 ? 'warning' : 'danger')),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        return 'Technician Financial Overview';
    }

    public function getDescription(): ?string
    {
        return 'Overview of outstanding payments and performance metrics for technicians';
    }
}
