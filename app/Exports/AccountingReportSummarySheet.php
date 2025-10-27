<?php

namespace App\Exports;

use App\Models\AccountingReport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AccountingReportSummarySheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
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
        $data[] = ['COMPREHENSIVE ACCOUNTING REPORT - FINANCIAL SUMMARY'];
        $data[] = ['Generated on: ' . now()->format('d M Y H:i:s')];
        $data[] = ['Period: ' . AccountingReport::getPeriodLabel($this->filters)];
        $data[] = [''];

        // Financial Overview
        $overview = AccountingReport::getAccountingOverview($this->filters);
        $data[] = ['FINANCIAL OVERVIEW'];
        $data[] = [''];
        $data[] = ['Total Revenue', 'Rp ' . number_format($overview->total_revenue, 0, ',', '.')];
        $data[] = ['Total Cost', 'Rp ' . number_format($overview->total_cost, 0, ',', '.')];
        $data[] = ['Gross Profit', 'Rp ' . number_format($overview->gross_profit, 0, ',', '.')];
        $data[] = ['Profit Margin', $overview->profit_margin . '%'];
        $data[] = [''];

        // Revenue Breakdown
        $revenueBreakdown = AccountingReport::getRevenueBreakdown($this->filters);
        $data[] = ['REVENUE BREAKDOWN'];
        $data[] = ['Source', 'Amount', 'Percentage'];
        $data[] = ['Income Revenue', 'Rp ' . number_format($revenueBreakdown->income_revenue, 0, ',', '.'), $revenueBreakdown->income_percentage . '%'];
        $data[] = ['Product Sales Revenue', 'Rp ' . number_format($revenueBreakdown->product_revenue, 0, ',', '.'), $revenueBreakdown->product_percentage . '%'];
        $data[] = ['Service Revenue', 'Rp ' . number_format($revenueBreakdown->service_revenue, 0, ',', '.'), $revenueBreakdown->service_percentage . '%'];
        $data[] = ['Total Revenue', 'Rp ' . number_format($revenueBreakdown->total_revenue, 0, ',', '.'), '100%'];
        $data[] = [''];

        // Cost Breakdown
        $costBreakdown = AccountingReport::getCostBreakdown($this->filters);
        $data[] = ['COST BREAKDOWN'];
        $data[] = ['Category', 'Amount', 'Percentage'];
        $data[] = ['Operating Expenses', 'Rp ' . number_format($costBreakdown->expense_cost, 0, ',', '.'), $costBreakdown->expense_percentage . '%'];
        $data[] = ['Product Costs (FIFO COGS)', 'Rp ' . number_format($costBreakdown->product_cost, 0, ',', '.'), $costBreakdown->product_percentage . '%'];
        $data[] = ['Service Costs', 'Rp ' . number_format($costBreakdown->service_cost, 0, ',', '.'), $costBreakdown->service_percentage . '%'];
        $data[] = ['Supplier Purchase Costs', 'Rp ' . number_format($costBreakdown->supplier_cost, 0, ',', '.'), $costBreakdown->supplier_percentage . '%'];
        $data[] = ['Total Cost', 'Rp ' . number_format($costBreakdown->total_cost, 0, ',', '.'), '100%'];
        $data[] = [''];

        // Outstanding Balances
        $debtAnalysis = AccountingReport::getDebtAnalysis($this->filters);
        $data[] = ['OUTSTANDING BALANCES'];
        $data[] = [''];
        $data[] = ['Receivables from Customers', 'Rp ' . number_format($debtAnalysis->receivables_from_customers, 0, ',', '.')];
        $data[] = ['Debt to Suppliers', 'Rp ' . number_format($debtAnalysis->debt_to_suppliers, 0, ',', '.')];

        // Format net position dengan tanda yang benar
        $netPositionFormatted = ($debtAnalysis->net_debt_position >= 0 ? '+' : '') .
                               'Rp ' . number_format(abs($debtAnalysis->net_debt_position), 0, ',', '.');
        $data[] = ['Net Position', $netPositionFormatted];
        $data[] = [''];

        // Key Performance Indicators
        $data[] = ['KEY PERFORMANCE INDICATORS'];
        $data[] = [''];
        $data[] = ['Profit Margin', $overview->profit_margin . '%'];
        $data[] = ['Revenue per Source (Avg)', 'Rp ' . number_format($overview->total_revenue / 3, 0, ',', '.')];
        $data[] = ['Cost per Category (Avg)', 'Rp ' . number_format($overview->total_cost / 4, 0, ',', '.')];

        // Net Position Analysis
        $netPositionStatus = $debtAnalysis->net_debt_position >= 0
            ? 'We\'re owed more than we owe (Good position)'
            : 'We owe more than we\'re owed (Requires attention)';
        $data[] = ['Net Position Status', $netPositionStatus];
        $data[] = [''];

        // ProductBatch Summary
        $data[] = ['INVENTORY SUMMARY (ProductBatch)'];
        $data[] = [''];

        // Get current inventory status
        $currentInventoryValue = \App\Models\ProductBatch::where('quantity', '>', 0)
            ->selectRaw('SUM(quantity * cost_price) as total_value')
            ->first()->total_value ?? 0;

        $totalBatches = \App\Models\ProductBatch::where('quantity', '>', 0)->count();
        $totalProducts = \App\Models\ProductBatch::where('quantity', '>', 0)
            ->distinct('product_id')
            ->count('product_id');

        $data[] = ['Current Inventory Value', 'Rp ' . number_format($currentInventoryValue, 0, ',', '.')];
        $data[] = ['Active Product Batches', number_format($totalBatches)];
        $data[] = ['Products in Stock', number_format($totalProducts)];

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
                    'startColor' => ['rgb' => '1F2937']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER
                ]
            ]
        ];
    }

    public function title(): string
    {
        return 'Financial Summary';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 35,
            'B' => 25,
            'C' => 15,
        ];
    }
}
