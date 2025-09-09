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

class AccountingReportCashFlowSheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
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
        $data[] = ['CASH FLOW & DEBT ANALYSIS REPORT'];
        $data[] = ['Generated on: ' . now()->format('d M Y H:i:s')];
        $data[] = ['Period: ' . AccountingReport::getPeriodLabel($this->filters)];
        $data[] = [''];

        // Cash Flow Analysis
        $cashFlow = AccountingReport::getCashFlowAnalysis($this->filters);
        $data[] = ['ACTUAL CASH FLOW ANALYSIS'];
        $data[] = [''];
        $data[] = ['CASH INFLOWS'];
        $data[] = ['Direct Income', 'Rp ' . number_format(AccountingReport::getAccountingOverview($this->filters)->income_revenue, 0, ',', '.')];
        $data[] = ['Customer Payments (Products)', 'Rp ' . number_format(AccountingReport::getAccountingOverview($this->filters)->product_revenue, 0, ',', '.')];
        $data[] = ['Customer Payments (Services)', 'Rp ' . number_format(AccountingReport::getAccountingOverview($this->filters)->service_revenue, 0, ',', '.')];
        $data[] = ['Total Cash In', 'Rp ' . number_format($cashFlow->actual_cash_in, 0, ',', '.')];
        $data[] = [''];

        $data[] = ['CASH OUTFLOWS'];
        $data[] = ['Operating Expenses', 'Rp ' . number_format(AccountingReport::getAccountingOverview($this->filters)->expense_cost, 0, ',', '.')];
        $data[] = ['Supplier Payments', 'Rp ' . number_format(AccountingReport::getAccountingOverview($this->filters)->supplier_cost, 0, ',', '.')];
        $data[] = ['Technician Payments', 'Rp ' . number_format(AccountingReport::getAccountingOverview($this->filters)->service_cost, 0, ',', '.')];
        $data[] = ['Total Cash Out', 'Rp ' . number_format($cashFlow->actual_cash_out, 0, ',', '.')];
        $data[] = [''];

        $data[] = ['NET CASH FLOW'];
        $data[] = ['Net Actual Cash Flow', 'Rp ' . number_format($cashFlow->net_actual_cash_flow, 0, ',', '.')];
        $data[] = ['Cash Flow Status', $cashFlow->net_actual_cash_flow > 0 ? 'Positive' : 'Negative'];
        $data[] = ['Cash Flow Ratio', $cashFlow->actual_cash_out > 0 ? round(($cashFlow->actual_cash_in / $cashFlow->actual_cash_out), 2) . ':1' : 'N/A'];
        $data[] = [''];

        // Outstanding Balances
        $debtAnalysis = AccountingReport::getDebtAnalysis($this->filters);
        $data[] = ['OUTSTANDING BALANCES ANALYSIS'];
        $data[] = [''];
        $data[] = ['RECEIVABLES (Money Owed to Us)'];
        $data[] = ['Product Orders Outstanding', 'Rp ' . number_format($debtAnalysis->product_outstanding, 0, ',', '.')];
        $data[] = ['Service Orders Outstanding', 'Rp ' . number_format($debtAnalysis->service_outstanding, 0, ',', '.')];
        $data[] = ['Total Receivables', 'Rp ' . number_format($debtAnalysis->receivables_from_customers, 0, ',', '.')];
        $data[] = [''];

        $data[] = ['PAYABLES (Money We Owe)'];
        $data[] = ['Supplier Orders Outstanding', 'Rp ' . number_format($debtAnalysis->supplier_outstanding, 0, ',', '.')];
        $data[] = ['Total Payables', 'Rp ' . number_format($debtAnalysis->debt_to_suppliers, 0, ',', '.')];
        $data[] = [''];

        $data[] = ['NET POSITION'];
        $data[] = ['Net Outstanding Balance', 'Rp ' . number_format($debtAnalysis->net_debt_position, 0, ',', '.')];
        $data[] = ['Position Status', $debtAnalysis->net_debt_position > 0 ? 'Net Debt Position' : 'Net Credit Position'];
        $data[] = ['Debt to Receivables Ratio', $debtAnalysis->receivables_from_customers > 0 ? round(($debtAnalysis->debt_to_suppliers / $debtAnalysis->receivables_from_customers), 2) . ':1' : 'N/A'];
        $data[] = [''];

        // Working Capital Analysis
        $data[] = ['WORKING CAPITAL ANALYSIS'];
        $data[] = [''];
        $data[] = ['Current Assets (Receivables)', 'Rp ' . number_format($debtAnalysis->receivables_from_customers, 0, ',', '.')];
        $data[] = ['Current Liabilities (Payables)', 'Rp ' . number_format($debtAnalysis->debt_to_suppliers, 0, ',', '.')];
        $data[] = ['Working Capital', 'Rp ' . number_format($debtAnalysis->receivables_from_customers - $debtAnalysis->debt_to_suppliers, 0, ',', '.')];

        $currentRatio = $debtAnalysis->debt_to_suppliers > 0 ?
                       round($debtAnalysis->receivables_from_customers / $debtAnalysis->debt_to_suppliers, 2) :
                       'Infinite';
        $data[] = ['Current Ratio', $currentRatio];
        $data[] = [''];

        // Cash Flow Projections
        $totalNetPosition = $cashFlow->net_actual_cash_flow + ($debtAnalysis->receivables_from_customers - $debtAnalysis->debt_to_suppliers);
        $data[] = ['PROJECTED CASH POSITION'];
        $data[] = [''];
        $data[] = ['If All Receivables Collected', 'Rp ' . number_format($cashFlow->net_actual_cash_flow + $debtAnalysis->receivables_from_customers, 0, ',', '.')];
        $data[] = ['If All Payables Settled', 'Rp ' . number_format($cashFlow->net_actual_cash_flow - $debtAnalysis->debt_to_suppliers, 0, ',', '.')];
        $data[] = ['Net Projected Position', 'Rp ' . number_format($totalNetPosition, 0, ',', '.')];

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
                    'startColor' => ['rgb' => '059669']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER
                ]
            ]
        ];
    }

    public function title(): string
    {
        return 'Cash Flow Analysis';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 25,
            'C' => 15,
        ];
    }
}
