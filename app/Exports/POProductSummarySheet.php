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
use PhpOffice\PhpSpreadsheet\Style\Font;
use Carbon\Carbon;

class POProductSummarySheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function array(): array
    {
        $data = [];

        // Header Information
        $data[] = ['PO PRODUCT REPORT - SUMMARY'];
        $data[] = ['Generated on: ' . now()->format('d M Y H:i:s')];
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

        // By Branch Analysis
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

        $monthlyTrends = POReportProduct::getFilteredMonthlyTrends($this->filters);
        if ($monthlyTrends->count() > 0) {
            $data[] = ['MONTHLY TRENDS'];
            $data[] = ['Period', 'Orders', 'Total Value', 'Received', 'Outstanding', 'Payment Rate'];

            $trendsToShow = $monthlyTrends;
            foreach ($trendsToShow as $trend) {
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
            ],
            // Style for section headers
            'A8' => ['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1F2937']]],
            'A13' => ['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1F2937']]],
            'A19' => ['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1F2937']]],
            // Header rows styling
            9 => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']]],
            14 => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']]],
            20 => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']]],
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
        ];
    }
}
