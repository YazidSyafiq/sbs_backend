<?php

namespace App\Exports;

use App\Models\POReportProduct;
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

class POProductSummarySheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
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
        $data[] = ['PO PRODUCT REPORT - SUMMARY'];
        $data[] = ['Generated on: ' . now()->format('d M Y H:i:s')];

        if ($user && $user->hasRole('User') && $user->branch) {
            $data[] = ['Branch: ' . $user->branch->name];
        }

        $data[] = [''];

        // Filter Information
        if (POReportProduct::hasActiveFilters($this->filters)) {
            $data[] = ['APPLIED FILTERS:'];
            $filterSummary = POReportProduct::getFilterSummary($this->filters);
            foreach ($filterSummary as $filter) {
                $data[] = ['• ' . $filter];
            }
            $data[] = [''];
        }

        // Overview Statistics
        $overviewStats = POReportProduct::getFilteredOverviewStats($this->filters);
        $data[] = ['FINANCIAL OVERVIEW'];
        $data[] = ['Metric', 'Value', 'Percentage'];
        $data[] = ['Total Purchase Orders', number_format($overviewStats->total_count), ''];
        $data[] = ['Total PO Value', 'Rp ' . number_format($overviewStats->total_po_amount, 0, ',', '.'), '100%'];
        $data[] = ['Amount Received', 'Rp ' . number_format($overviewStats->paid_amount, 0, ',', '.'), $overviewStats->payment_rate . '%'];
        $data[] = ['Outstanding Debt', 'Rp ' . number_format($overviewStats->outstanding_debt, 0, ',', '.'), (100 - $overviewStats->payment_rate) . '%'];
        $data[] = [''];

        // PROFIT ANALYSIS - ONLY for non-User roles
        if ($user && !$user->hasRole('User')) {
            $profitStats = POReportProduct::getFilteredProfitOverview($this->filters);
            $data[] = ['REALIZED PROFIT & LOSS ANALYSIS'];
            $data[] = ['Metric', 'Value', 'Details'];
            $data[] = ['Realized Cost', 'Rp ' . number_format($profitStats->total_cost, 0, ',', '.'), 'Cost from paid orders'];
            $data[] = ['Realized Revenue', 'Rp ' . number_format($profitStats->total_revenue, 0, ',', '.'), 'Revenue from paid orders'];
            $data[] = ['Realized Profit', 'Rp ' . number_format($profitStats->total_profit, 0, ',', '.'), 'Profit from paid orders'];
            $data[] = ['Realized Profit Margin', $profitStats->profit_margin . '%', 'Margin from paid orders'];
            $data[] = ['Items Sold', number_format($profitStats->total_items), 'From paid orders'];
            $data[] = ['Potential Profit', 'Rp ' . number_format($profitStats->potential_profit, 0, ',', '.'), 'From unpaid orders'];
            $data[] = [''];
        }

        // By Type Analysis
        $data[] = ['ANALYSIS BY TYPE'];
        $data[] = ['Type', 'Count', 'Total Value', 'Received', 'Outstanding', 'Payment Rate'];
        $data[] = [
            'Credit Purchase',
            number_format($overviewStats->credit_count),
            'Rp ' . number_format($overviewStats->credit_total_amount, 0, ',', '.'),
            'Rp ' . number_format($overviewStats->credit_paid_amount, 0, ',', '.'),
            'Rp ' . number_format($overviewStats->credit_outstanding, 0, ',', '.'),
            $overviewStats->credit_payment_rate . '%'
        ];
        $data[] = [
            'Cash Purchase',
            number_format($overviewStats->cash_count),
            'Rp ' . number_format($overviewStats->cash_total_amount, 0, ',', '.'),
            'Rp ' . number_format($overviewStats->cash_paid_amount, 0, ',', '.'),
            'Rp ' . number_format($overviewStats->cash_outstanding, 0, ',', '.'),
            $overviewStats->cash_payment_rate . '%'
        ];
        $data[] = [''];

        // TOP PROFITABLE PRODUCTS - ONLY for non-User roles
        if ($user && !$user->hasRole('User')) {
            $topProducts = POReportProduct::getTopProfitableProducts($this->filters, 10);
            if ($topProducts->count() > 0) {
                $data[] = ['TOP PROFITABLE PRODUCTS (FROM PAID ORDERS)'];
                $data[] = ['Product Name', 'Total Profit', 'Profit Margin', 'Quantity Sold', 'Revenue', 'Cost'];
                foreach ($topProducts as $product) {
                    $data[] = [
                        $product->product_name,
                        'Rp ' . number_format($product->total_profit, 0, ',', '.'),
                        $product->profit_margin . '%',
                        number_format($product->total_quantity),
                        'Rp ' . number_format($product->total_revenue, 0, ',', '.'),
                        'Rp ' . number_format($product->total_cost, 0, ',', '.')
                    ];
                }
                $data[] = [''];
            }
        }

        // By Branch Analysis
        if ($user && !$user->hasRole('User')) {
            $branchStats = POReportProduct::getFilteredProfitByBranch($this->filters);
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
            $branchStats = POReportProduct::getFilteredAccountingSummaryByBranch($this->filters);
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
            $monthlyTrends = POReportProduct::getFilteredProfitTrends($this->filters);
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
            $monthlyTrends = POReportProduct::getFilteredMonthlyTrends($this->filters);
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
