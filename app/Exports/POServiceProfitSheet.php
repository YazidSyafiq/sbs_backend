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

class POServiceProfitSheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
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
        $data[] = ['REALIZED SERVICE PROFIT & LOSS ANALYSIS REPORT'];
        $data[] = ['Generated on: ' . now()->format('d M Y H:i:s')];
        $data[] = ['Note: Profit calculated from PAID service orders only'];
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

        // Overall Profit Summary
        $profitStats = POReportService::getFilteredProfitOverview($this->filters);
        $data[] = ['REALIZED SERVICE PROFIT OVERVIEW'];
        $data[] = [''];
        $data[] = ['Realized Cost (from paid orders)', 'Rp ' . number_format($profitStats->total_cost, 0, ',', '.')];
        $data[] = ['Realized Revenue (from paid orders)', 'Rp ' . number_format($profitStats->total_revenue, 0, ',', '.')];
        $data[] = ['Realized Profit', 'Rp ' . number_format($profitStats->total_profit, 0, ',', '.')];
        $data[] = ['Realized Profit Margin', $profitStats->profit_margin . '%'];
        $data[] = ['Paid Service Orders Count', number_format($profitStats->total_orders)];
        $data[] = ['Services Delivered (from paid orders)', number_format($profitStats->total_services)];
        $data[] = ['Average Profit per Paid Order', 'Rp ' . number_format($profitStats->total_orders > 0 ? $profitStats->total_profit / $profitStats->total_orders : 0, 0, ',', '.')];
        $data[] = [''];

        // Potential Profit Section
        $data[] = ['POTENTIAL PROFIT (FROM UNPAID ORDERS)'];
        $data[] = [''];
        $data[] = ['Unpaid Service Orders Count', number_format($profitStats->unpaid_orders)];
        $data[] = ['Potential Revenue', 'Rp ' . number_format($profitStats->outstanding_revenue, 0, ',', '.')];
        $data[] = ['Potential Cost', 'Rp ' . number_format($profitStats->outstanding_cost, 0, ',', '.')];
        $data[] = ['Potential Profit', 'Rp ' . number_format($profitStats->potential_profit, 0, ',', '.')];
        $data[] = [''];

        // Top Profitable Services
        $topServices = POReportService::getTopProfitableServices($this->filters, 20);
        if ($topServices->count() > 0) {
            $data[] = ['TOP 20 PROFITABLE SERVICES (FROM PAID ORDERS ONLY)'];
            $data[] = ['Rank', 'Service Name', 'Code', 'Services Count', 'Total Cost', 'Total Revenue', 'Realized Profit', 'Margin %', 'Avg Cost/Service', 'Avg Revenue/Service', 'Avg Profit/Service'];

            $rank = 1;
            foreach ($topServices as $service) {
                $data[] = [
                    $rank++,
                    $service->service_name,
                    $service->service_code,
                    number_format($service->total_quantity),
                    'Rp ' . number_format($service->total_cost, 0, ',', '.'),
                    'Rp ' . number_format($service->total_revenue, 0, ',', '.'),
                    'Rp ' . number_format($service->total_profit, 0, ',', '.'),
                    $service->profit_margin . '%',
                    'Rp ' . number_format($service->avg_cost_per_service, 0, ',', '.'),
                    'Rp ' . number_format($service->avg_revenue_per_service, 0, ',', '.'),
                    'Rp ' . number_format($service->avg_profit_per_service, 0, ',', '.')
                ];
            }
            $data[] = [''];
        }

        // Profit by Branch
        $branchProfits = POReportService::getFilteredProfitByBranch($this->filters);
        if ($branchProfits->count() > 0) {
            $data[] = ['REALIZED SERVICE PROFIT ANALYSIS BY BRANCH'];
            $data[] = ['Branch Name', 'Branch Code', 'Total Orders', 'Paid Orders', 'Services Delivered', 'Total Cost', 'Total Revenue', 'Realized Profit', 'Profit Margin %', 'Potential Profit'];

            foreach ($branchProfits as $branch) {
                $data[] = [
                    $branch->branch_name,
                    $branch->branch_code,
                    number_format($branch->total_pos),
                    number_format($branch->paid_pos),
                    number_format($branch->total_services),
                    'Rp ' . number_format($branch->total_cost, 0, ',', '.'),
                    'Rp ' . number_format($branch->total_revenue, 0, ',', '.'),
                    'Rp ' . number_format($branch->total_profit, 0, ',', '.'),
                    $branch->profit_margin . '%',
                    'Rp ' . number_format($branch->potential_profit, 0, ',', '.')
                ];
            }
            $data[] = [''];
        }

        // Monthly Profit Trends
        $monthlyTrends = POReportService::getFilteredProfitTrends($this->filters);
        if ($monthlyTrends->count() > 0) {
            $data[] = ['MONTHLY REALIZED SERVICE PROFIT TRENDS'];
            $data[] = ['Period', 'Total Orders', 'Paid Orders', 'Total Cost', 'Total Revenue', 'Realized Profit', 'Profit Margin %'];

            foreach ($monthlyTrends as $trend) {
                $data[] = [
                    $trend->period_name,
                    number_format($trend->total_pos),
                    number_format($trend->paid_pos),
                    'Rp ' . number_format($trend->total_cost, 0, ',', '.'),
                    'Rp ' . number_format($trend->total_revenue, 0, ',', '.'),
                    'Rp ' . number_format($trend->total_profit, 0, ',', '.'),
                    $trend->profit_margin . '%'
                ];
            }
            $data[] = [''];
        }

        // Profit Performance Analysis
        $data[] = ['PROFIT PERFORMANCE ANALYSIS'];
        $data[] = [''];

        if ($topServices->count() > 0) {
            $positiveMarginServices = $topServices->filter(fn($s) => $s->profit_margin > 0)->count();
            $highMarginServices = $topServices->filter(fn($s) => $s->profit_margin >= 20)->count();
            $lowMarginServices = $topServices->filter(fn($s) => $s->profit_margin > 0 && $s->profit_margin < 10)->count();

            $data[] = ['Services with Positive Margin', $positiveMarginServices . ' / ' . $topServices->count()];
            $data[] = ['High Margin Services (≥20%)', $highMarginServices . ' / ' . $topServices->count()];
            $data[] = ['Low Margin Services (0-10%)', $lowMarginServices . ' / ' . $topServices->count()];
            $data[] = [''];
        }

        if ($branchProfits->count() > 0) {
            $topBranch = $branchProfits->first();
            if ($topBranch) {
                $data[] = ['Best Performing Branch (Realized)', $topBranch->branch_name . ' (Rp ' . number_format($topBranch->total_profit, 0, ',', '.') . ')'];
            }

            $avgBranchMargin = $branchProfits->avg('profit_margin');
            if ($avgBranchMargin > 0) {
                $data[] = ['Average Branch Margin (Realized)', round($avgBranchMargin, 2) . '%'];
            }
        }

        $data[] = [''];
        $data[] = ['SUMMARY'];
        $data[] = ['Total Realized vs Potential Profit', 'Realized: Rp ' . number_format($profitStats->total_profit, 0, ',', '.') . ' | Potential: Rp ' . number_format($profitStats->potential_profit, 0, ',', '.')];
        $data[] = ['Cash Flow Realization Rate', round(($profitStats->total_orders / ($profitStats->total_orders + $profitStats->unpaid_orders)) * 100, 1) . '% of service orders paid'];

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
                    'startColor' => ['rgb' => '7C3AED']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER
                ]
            ]
        ];
    }

    public function title(): string
    {
        return 'Profit Analysis';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 20,
            'C' => 15,
            'D' => 12,
            'E' => 12,
            'F' => 18,
            'G' => 18,
            'H' => 18,
            'I' => 12,
            'J' => 15,
            'K' => 15,
            'L' => 15,
        ];
    }
}
