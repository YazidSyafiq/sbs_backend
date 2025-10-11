<?php

namespace App\Exports;

use App\Models\POReportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class POServiceTechnicianSheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function array(): array
    {
        $data = [];

        // Header
        $data[] = ['TECHNICIAN FINANCIAL & PERFORMANCE ANALYSIS REPORT'];
        $data[] = ['Generated on: ' . now()->format('d M Y H:i:s')];
        $data[] = ['Note: Outstanding debt calculated from unpaid credit service orders'];
        $data[] = [''];

        // Filter Information
        if (POReportService::hasActiveFilters($this->filters)) {
            $data[] = ['APPLIED FILTERS:'];
            $filterSummary = POReportService::getFilterSummary($this->filters);
            foreach ($filterSummary as $filter) {
                $data[] = ['• ' . $filter];
            }
            $data[] = [''];
        }

        // Overall Technician Debt Summary
        $technicianDebtStats = POReportService::getTechnicianDebtOverview($this->filters);
        $data[] = ['TECHNICIAN DEBT OVERVIEW'];
        $data[] = [''];
        $data[] = ['Total Outstanding Debt to Technicians', 'Rp ' . number_format($technicianDebtStats->total_debt_to_technicians, 0, ',', '.')];
        $data[] = ['Total Active Technicians', number_format($technicianDebtStats->total_technicians)];
        $data[] = ['Technicians with Outstanding Debt', number_format($technicianDebtStats->technicians_with_debt)];
        $data[] = ['Average Debt per Technician', 'Rp ' . number_format($technicianDebtStats->average_debt_per_technician, 0, ',', '.')];
        $data[] = ['Debt Percentage of Total Cost', $technicianDebtStats->debt_percentage . '%'];
        $data[] = ['Total Service Orders', number_format($technicianDebtStats->total_services)];
        $data[] = ['Completed Service Orders', number_format($technicianDebtStats->completed_services)];
        $data[] = ['Service Completion Rate', $technicianDebtStats->completion_rate . '%'];
        $data[] = ['Total Realized Profit', 'Rp ' . number_format($technicianDebtStats->total_realized_profit, 0, ',', '.')];
        $data[] = ['Total Potential Profit', 'Rp ' . number_format($technicianDebtStats->total_potential_profit, 0, ',', '.')];
        $data[] = ['Profit Realization Rate', $technicianDebtStats->profit_realization_rate . '%'];
        $data[] = [''];

        // Detailed Technician Analysis
        $technicianAnalysis = POReportService::getFilteredTechnicianAnalysis($this->filters);
        if ($technicianAnalysis->count() > 0) {
            $data[] = ['DETAILED TECHNICIAN ANALYSIS'];
            $data[] = [
                'Technician Name',
                'Technician Code',
                'Total Services',
                'Completed Services',
                'Completion Rate %',
                'Total Service Value',
                'Total Cost Owed',
                'Outstanding Debt',
                'Paid Cost',
                'Unpaid Cost',
                'Realized Profit',
                'Potential Profit',
                'Profit Margin %',
                'Avg Service Value',
                'Avg Cost per Service',
                'Current Piutang (DB)',
                'Total PO Recorded (DB)'
            ];

            foreach ($technicianAnalysis as $tech) {
                $data[] = [
                    $tech->technician_name,
                    $tech->technician_code,
                    number_format($tech->total_services),
                    number_format($tech->completed_services),
                    $tech->completion_rate . '%',
                    'Rp ' . number_format($tech->total_po_value, 0, ',', '.'),
                    'Rp ' . number_format($tech->total_cost_owed, 0, ',', '.'),
                    'Rp ' . number_format($tech->actual_debt, 0, ',', '.'),
                    'Rp ' . number_format($tech->paid_cost, 0, ',', '.'),
                    'Rp ' . number_format($tech->unpaid_cost, 0, ',', '.'),
                    'Rp ' . number_format($tech->realized_profit, 0, ',', '.'),
                    'Rp ' . number_format($tech->potential_profit, 0, ',', '.'),
                    $tech->profit_margin . '%',
                    'Rp ' . number_format($tech->average_service_value, 0, ',', '.'),
                    'Rp ' . number_format($tech->average_cost_per_service, 0, ',', '.'),
                    'Rp ' . number_format($tech->current_piutang, 0, ',', '.'),
                    'Rp ' . number_format($tech->total_po_recorded, 0, ',', '.')
                ];
            }
            $data[] = [''];
        }

        // Top Technicians by Outstanding Debt
        $topDebtTechnicians = $technicianAnalysis->filter(fn($t) => $t->actual_debt > 0)
                                                 ->sortByDesc('actual_debt')
                                                 ->take(15);
        if ($topDebtTechnicians->count() > 0) {
            $data[] = ['TOP 15 TECHNICIANS WITH HIGHEST OUTSTANDING DEBT'];
            $data[] = [
                'Rank',
                'Technician Name',
                'Outstanding Debt',
                'Unpaid Services',
                'Total Services',
                'Completion Rate %',
                'Potential Profit Lost',
                'Debt vs Total Cost %'
            ];

            $rank = 1;
            foreach ($topDebtTechnicians as $tech) {
                $debtPercentage = $tech->total_cost_owed > 0 ?
                                 round(($tech->actual_debt / $tech->total_cost_owed) * 100, 1) : 0;

                $data[] = [
                    $rank++,
                    $tech->technician_name,
                    'Rp ' . number_format($tech->actual_debt, 0, ',', '.'),
                    number_format($tech->unpaid_services),
                    number_format($tech->total_services),
                    $tech->completion_rate . '%',
                    'Rp ' . number_format($tech->potential_profit, 0, ',', '.'),
                    $debtPercentage . '%'
                ];
            }
            $data[] = [''];
        }

        // Top Performers by Service Count
        $topPerformers = $technicianAnalysis->sortByDesc('total_services')->take(10);
        if ($topPerformers->count() > 0) {
            $data[] = ['TOP 10 MOST ACTIVE TECHNICIANS'];
            $data[] = [
                'Rank',
                'Technician Name',
                'Total Services',
                'Completed Services',
                'Completion Rate %',
                'Total Service Value',
                'Realized Profit',
                'Profit Margin %',
                'Outstanding Debt'
            ];

            $rank = 1;
            foreach ($topPerformers as $tech) {
                $data[] = [
                    $rank++,
                    $tech->technician_name,
                    number_format($tech->total_services),
                    number_format($tech->completed_services),
                    $tech->completion_rate . '%',
                    'Rp ' . number_format($tech->total_po_value, 0, ',', '.'),
                    'Rp ' . number_format($tech->realized_profit, 0, ',', '.'),
                    $tech->profit_margin . '%',
                    'Rp ' . number_format($tech->actual_debt, 0, ',', '.')
                ];
            }
            $data[] = [''];
        }

        // Most Profitable Technicians
        $topProfitTechnicians = $technicianAnalysis->sortByDesc('realized_profit')->take(10);
        if ($topProfitTechnicians->count() > 0) {
            $data[] = ['TOP 10 MOST PROFITABLE TECHNICIANS'];
            $data[] = [
                'Rank',
                'Technician Name',
                'Realized Profit',
                'Profit Margin %',
                'Total Services',
                'Paid Services',
                'Service Value',
                'Outstanding Debt'
            ];

            $rank = 1;
            foreach ($topProfitTechnicians as $tech) {
                $data[] = [
                    $rank++,
                    $tech->technician_name,
                    'Rp ' . number_format($tech->realized_profit, 0, ',', '.'),
                    $tech->profit_margin . '%',
                    number_format($tech->total_services),
                    number_format($tech->paid_services),
                    'Rp ' . number_format($tech->total_po_value, 0, ',', '.'),
                    'Rp ' . number_format($tech->actual_debt, 0, ',', '.')
                ];
            }
            $data[] = [''];
        }

        // Risk Analysis
        $data[] = ['RISK ANALYSIS'];
        $data[] = [''];

        $highRiskTechnicians = $technicianAnalysis->filter(fn($t) => $t->actual_debt >= 5000000);
        $mediumRiskTechnicians = $technicianAnalysis->filter(fn($t) => $t->actual_debt >= 1000000 && $t->actual_debt < 5000000);
        $lowCompletionTechnicians = $technicianAnalysis->filter(fn($t) => $t->completion_rate < 60);

        $data[] = ['High Risk Technicians (Debt ≥ 5M)', $highRiskTechnicians->count() . ' technicians'];
        $data[] = ['Medium Risk Technicians (Debt 1M-5M)', $mediumRiskTechnicians->count() . ' technicians'];
        $data[] = ['Low Completion Rate Technicians (<60%)', $lowCompletionTechnicians->count() . ' technicians'];

        if ($highRiskTechnicians->count() > 0) {
            $totalHighRiskDebt = $highRiskTechnicians->sum('actual_debt');
            $data[] = ['Total High Risk Debt', 'Rp ' . number_format($totalHighRiskDebt, 0, ',', '.')];
        }

        $data[] = [''];

        // Performance Summary
        $data[] = ['PERFORMANCE SUMMARY'];
        $data[] = [''];

        $avgCompletionRate = $technicianAnalysis->avg('completion_rate');
        $avgProfitMargin = $technicianAnalysis->where('profit_margin', '>', 0)->avg('profit_margin');
        $totalTechnicianRevenue = $technicianAnalysis->sum('total_po_value');
        $totalTechnicianProfit = $technicianAnalysis->sum('realized_profit');

        $data[] = ['Average Completion Rate', round($avgCompletionRate, 1) . '%'];
        $data[] = ['Average Profit Margin (Positive only)', round($avgProfitMargin ?: 0, 1) . '%'];
        $data[] = ['Total Technician Revenue', 'Rp ' . number_format($totalTechnicianRevenue, 0, ',', '.')];
        $data[] = ['Total Technician Profit', 'Rp ' . number_format($totalTechnicianProfit, 0, ',', '.')];
        $data[] = ['Debt to Revenue Ratio', $totalTechnicianRevenue > 0 ? round(($technicianDebtStats->total_debt_to_technicians / $totalTechnicianRevenue) * 100, 1) . '%' : '0%'];

        $data[] = [''];

        $data[] = ['RECOMMENDATIONS'];
        $data[] = [''];
        if ($technicianDebtStats->total_debt_to_technicians > 0) {
            $data[] = ['• Priority collection needed for ' . $technicianDebtStats->technicians_with_debt . ' technicians with outstanding debt'];
        }
        if ($avgCompletionRate < 80) {
            $data[] = ['• Service completion rate below target - review technician performance management'];
        }
        if ($technicianDebtStats->debt_percentage > 30) {
            $data[] = ['• High debt percentage - consider tightening credit policies for technicians'];
        }
        $data[] = ['• Regular monitoring recommended for technicians with completion rate below 70%'];

        return $data;
    }

    public function headings(): array
    {
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 16,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DC2626']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER
                ]
            ]
        ];
    }

    public function title(): string
    {
        return 'Technician Analysis';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 15,
            'C' => 12,
            'D' => 12,
            'E' => 12,
            'F' => 18,
            'G' => 18,
            'H' => 18,
            'I' => 18,
            'J' => 18,
            'K' => 18,
            'L' => 18,
            'M' => 12,
            'N' => 15,
            'O' => 15,
            'P' => 18,
            'Q' => 18,
        ];
    }
}
