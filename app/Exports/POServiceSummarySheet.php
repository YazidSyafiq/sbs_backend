<?php

namespace App\Exports;

use App\Models\POReportService;
use App\Models\Branch;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;
use Auth;

class POServiceSummarySheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function array(): array
    {
        $user = Auth::user();
        $data = [];

        // Header Information
        $data[] = ['PO SERVICE REPORT - SUMMARY'];
        $data[] = ['Generated on: ' . now()->format('d M Y H:i:s')];

        if ($user && $user->hasRole('User') && $user->branch) {
            $data[] = ['Branch: ' . $user->branch->name];
        }

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

        // Overview Statistics
        $overviewStats = POReportService::getFilteredOverviewStats($this->filters);
        $data[] = ['FINANCIAL OVERVIEW'];
        $data[] = ['Metric', 'Value', 'Percentage'];
        $data[] = ['Total Service Orders', number_format($overviewStats->total_count), ''];
        $data[] = ['Total Service Value', 'Rp ' . number_format($overviewStats->total_po_amount, 0, ',', '.'), '100%'];
        $data[] = ['Amount Received', 'Rp ' . number_format($overviewStats->paid_amount, 0, ',', '.'), $overviewStats->payment_rate . '%'];
        $data[] = ['Outstanding Debt', 'Rp ' . number_format($overviewStats->outstanding_debt, 0, ',', '.'), (100 - $overviewStats->payment_rate) . '%'];
        $data[] = [''];

        // PROFIT ANALYSIS - ONLY for non-User roles
        if ($user && !$user->hasRole('User')) {
            $profitStats = POReportService::getFilteredProfitOverview($this->filters);
            $data[] = ['REALIZED PROFIT & LOSS ANALYSIS'];
            $data[] = ['Metric', 'Value', 'Details'];
            $data[] = ['Realized Cost', 'Rp ' . number_format($profitStats->total_cost, 0, ',', '.'), 'Cost from paid service orders'];
            $data[] = ['Realized Revenue', 'Rp ' . number_format($profitStats->total_revenue, 0, ',', '.'), 'Revenue from paid service orders'];
            $data[] = ['Realized Profit', 'Rp ' . number_format($profitStats->total_profit, 0, ',', '.'), 'Profit from paid service orders'];
            $data[] = ['Realized Profit Margin', $profitStats->profit_margin . '%', 'Margin from paid service orders'];
            $data[] = ['Services Delivered', number_format($profitStats->total_services), 'From paid service orders'];
            $data[] = ['Potential Profit', 'Rp ' . number_format($profitStats->potential_profit, 0, ',', '.'), 'From unpaid service orders'];
            $data[] = [''];

            // TECHNICIAN DEBT ANALYSIS
            $technicianDebtStats = POReportService::getTechnicianDebtOverview($this->filters);
            $data[] = ['TECHNICIAN FINANCIAL ANALYSIS'];
            $data[] = ['Metric', 'Value', 'Details'];
            $data[] = ['Total Debt to Technicians', 'Rp ' . number_format($technicianDebtStats->total_debt_to_technicians, 0, ',', '.'), 'Outstanding payments to technicians'];
            $data[] = ['Active Technicians', number_format($technicianDebtStats->total_technicians), $technicianDebtStats->technicians_with_debt . ' have outstanding debt'];
            $data[] = ['Average Debt per Technician', 'Rp ' . number_format($technicianDebtStats->average_debt_per_technician, 0, ',', '.'), 'Average outstanding amount'];
            $data[] = ['Debt Percentage', $technicianDebtStats->debt_percentage . '%', 'Percentage of total cost owed'];
            $data[] = ['Service Completion Rate', $technicianDebtStats->completion_rate . '%', $technicianDebtStats->completed_services . ' of ' . $technicianDebtStats->total_services . ' completed'];
            $data[] = ['Profit Realization Rate', $technicianDebtStats->profit_realization_rate . '%', 'Percentage of profit realized from payments'];
            $data[] = [''];
        }

        // By Type Analysis
        $data[] = ['ANALYSIS BY TYPE'];
        $data[] = ['Type', 'Count', 'Total Value', 'Received', 'Outstanding', 'Payment Rate'];
        $data[] = [
            'Credit Service',
            number_format($overviewStats->credit_count),
            'Rp ' . number_format($overviewStats->credit_total_amount, 0, ',', '.'),
            'Rp ' . number_format($overviewStats->credit_paid_amount, 0, ',', '.'),
            'Rp ' . number_format($overviewStats->credit_outstanding, 0, ',', '.'),
            $overviewStats->credit_payment_rate . '%'
        ];
        $data[] = [
            'Cash Service',
            number_format($overviewStats->cash_count),
            'Rp ' . number_format($overviewStats->cash_total_amount, 0, ',', '.'),
            'Rp ' . number_format($overviewStats->cash_paid_amount, 0, ',', '.'),
            'Rp ' . number_format($overviewStats->cash_outstanding, 0, ',', '.'),
            $overviewStats->cash_payment_rate . '%'
        ];
        $data[] = [''];

        // TOP PROFITABLE SERVICES - ONLY for non-User roles
        if ($user && !$user->hasRole('User')) {
            $topServices = POReportService::getTopProfitableServices($this->filters, 10);
            if ($topServices->count() > 0) {
                $data[] = ['TOP PROFITABLE SERVICES (FROM PAID ORDERS)'];
                $data[] = ['Service Name', 'Total Profit', 'Profit Margin', 'Services Count', 'Revenue', 'Cost'];
                foreach ($topServices as $service) {
                    $data[] = [
                        $service->service_name,
                        'Rp ' . number_format($service->total_profit, 0, ',', '.'),
                        $service->profit_margin . '%',
                        number_format($service->total_quantity),
                        'Rp ' . number_format($service->total_revenue, 0, ',', '.'),
                        'Rp ' . number_format($service->total_cost, 0, ',', '.')
                    ];
                }
                $data[] = [''];
            }

            // TOP TECHNICIANS BY DEBT
            $topTechnicians = POReportService::getFilteredTechnicianAnalysis($this->filters);
            $topDebtTechnicians = $topTechnicians->filter(fn($t) => $t->actual_debt > 0)->take(10);
            if ($topDebtTechnicians->count() > 0) {
                $data[] = ['TOP TECHNICIANS WITH OUTSTANDING DEBT'];
                $data[] = ['Technician Name', 'Outstanding Debt', 'Total Services', 'Completion Rate', 'Realized Profit', 'Potential Profit'];
                foreach ($topDebtTechnicians as $tech) {
                    $data[] = [
                        $tech->technician_name,
                        'Rp ' . number_format($tech->actual_debt, 0, ',', '.'),
                        number_format($tech->total_services),
                        $tech->completion_rate . '%',
                        'Rp ' . number_format($tech->realized_profit, 0, ',', '.'),
                        'Rp ' . number_format($tech->potential_profit, 0, ',', '.')
                    ];
                }
                $data[] = [''];
            }
        }

        // By Branch Analysis
        if ($user && !$user->hasRole('User')) {
            $branchStats = POReportService::getFilteredProfitByBranch($this->filters);
            if ($branchStats->count() > 0) {
                $data[] = ['REALIZED PROFIT ANALYSIS BY BRANCH'];
                $data[] = ['Branch', 'Paid Orders', 'Realized Profit', 'Profit Margin', 'Revenue', 'Cost', 'Potential Profit'];
                foreach ($branchStats as $branch) {
                    $data[] = [
                        $branch->branch_name,
                        number_format($branch->paid_pos),
                        'Rp ' . number_format($branch->total_profit, 0, ',', '.'),
                        $branch->profit_margin . '%',
                        'Rp ' . number_format($branch->total_revenue, 0, ',', '.'),
                        'Rp ' . number_format($branch->total_cost, 0, ',', '.'),
                        'Rp ' . number_format($branch->potential_profit, 0, ',', '.')
                    ];
                }
                $data[] = [''];
            }
        } else {
            $branchStats = POReportService::getFilteredAccountingSummaryByBranch($this->filters);
            if ($branchStats->count() > 0) {
                $data[] = ['ANALYSIS BY BRANCH'];
                $data[] = ['Branch', 'Orders', 'Total Value', 'Received', 'Outstanding', 'Payment Rate'];
                foreach ($branchStats as $branch) {
                    $data[] = [
                        $branch->branch_name,
                        number_format($branch->total_pos),
                        'Rp ' . number_format($branch->total_po_amount, 0, ',', '.'),
                        'Rp ' . number_format($branch->paid_amount, 0, ',', '.'),
                        'Rp ' . number_format($branch->outstanding_debt, 0, ',', '.'),
                        $branch->payment_rate . '%'
                    ];
                }
                $data[] = [''];
            }
        }

        // Monthly Trends
        if ($user && !$user->hasRole('User')) {
            $monthlyTrends = POReportService::getFilteredProfitTrends($this->filters);
            if ($monthlyTrends->count() > 0) {
                $data[] = ['MONTHLY REALIZED PROFIT TRENDS'];
                $data[] = ['Period', 'Paid Orders', 'Revenue', 'Cost', 'Profit', 'Margin'];

                foreach ($monthlyTrends as $trend) {
                    $data[] = [
                        $trend->period_name,
                        number_format($trend->paid_pos),
                        'Rp ' . number_format($trend->total_revenue, 0, ',', '.'),
                        'Rp ' . number_format($trend->total_cost, 0, ',', '.'),
                        'Rp ' . number_format($trend->total_profit, 0, ',', '.'),
                        $trend->profit_margin . '%'
                    ];
                }
            }
        } else {
            $monthlyTrends = POReportService::getFilteredMonthlyTrends($this->filters);
            if ($monthlyTrends->count() > 0) {
                $data[] = ['MONTHLY TRENDS'];
                $data[] = ['Period', 'Orders', 'Total Value', 'Received', 'Outstanding', 'Payment Rate'];

                foreach ($monthlyTrends as $trend) {
                    $data[] = [
                        $trend->period_name,
                        number_format($trend->total_pos),
                        'Rp ' . number_format($trend->total_po_amount, 0, ',', '.'),
                        'Rp ' . number_format($trend->paid_amount, 0, ',', '.'),
                        'Rp ' . number_format($trend->outstanding_debt, 0, ',', '.'),
                        $trend->payment_rate . '%'
                    ];
                }
            }
        }

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
                    'startColor' => ['rgb' => '4F46E5']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER
                ]
            ]
        ];
    }

    public function title(): string
    {
        return 'Summary';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 20,
            'C' => 20,
            'D' => 20,
            'E' => 20,
            'F' => 15,
            'G' => 20,
        ];
    }
}
