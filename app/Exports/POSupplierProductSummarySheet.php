<?php

namespace App\Exports;

use App\Models\POReportSupplierProduct;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\ProductCategory;
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

class POSupplierProductSummarySheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
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
        $data[] = ['SUPPLIER-PRODUCT ANALYTICS REPORT - SUMMARY'];
        $data[] = ['Generated on: ' . now()->format('d M Y H:i:s')];
        $data[] = [''];

        // Filter Information
        if (POReportSupplierProduct::hasActiveFilters($this->filters)) {
            $data[] = ['APPLIED FILTERS:'];
            $filterSummary = POReportSupplierProduct::getFilterSummary($this->filters);
            foreach ($filterSummary as $filter) {
                $data[] = ['• ' . $filter];
            }
            $data[] = [''];
        }

        // Overview Statistics
        $overviewStats = POReportSupplierProduct::getFilteredOverviewStats($this->filters);
        $data[] = ['FINANCIAL OVERVIEW'];
        $data[] = ['Metric', 'Value', 'Percentage'];
        $data[] = ['Total Purchase Orders', number_format($overviewStats->total_count), ''];
        $data[] = ['Total PO Value', 'Rp ' . number_format($overviewStats->total_po_amount, 0, ',', '.'), '100%'];
        $data[] = ['Amount Paid to Suppliers', 'Rp ' . number_format($overviewStats->paid_amount, 0, ',', '.'), $overviewStats->payment_rate . '%'];
        $data[] = ['Outstanding Payments', 'Rp ' . number_format($overviewStats->outstanding_debt, 0, ',', '.'), (100 - $overviewStats->payment_rate) . '%'];
        $data[] = ['Total Quantity Ordered', number_format($overviewStats->total_quantity), ''];
        $data[] = ['Unique Suppliers', number_format($overviewStats->unique_suppliers), ''];
        $data[] = ['Unique Products', number_format($overviewStats->unique_products), ''];
        $data[] = [''];

        // By Type Analysis
        $data[] = ['ANALYSIS BY PURCHASE TYPE'];
        $data[] = ['Type', 'Count', 'Total Value', 'Amount Paid', 'Outstanding', 'Payment Rate'];
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

        // Top Suppliers Analysis
        $supplierStats = POReportSupplierProduct::getFilteredSupplierStats($this->filters);
        if ($supplierStats->count() > 0) {
            $data[] = ['TOP SUPPLIERS ANALYSIS'];
            $data[] = ['Supplier', 'Orders', 'Total Value', 'Products', 'Total Quantity', 'Payment Rate'];
            $topSuppliers = $supplierStats->take(10);
            foreach ($topSuppliers as $supplier) {
                $data[] = [
                    $supplier->supplier_name . ' (' . $supplier->supplier_code . ')',
                    number_format($supplier->total_pos),
                    'Rp ' . number_format($supplier->total_po_amount, 0, ',', '.'),
                    number_format($supplier->unique_products),
                    number_format($supplier->total_quantity),
                    $supplier->payment_rate . '%'
                ];
            }
            $data[] = [''];
        }

        // Top Products Analysis
        $productStats = POReportSupplierProduct::getFilteredProductStats($this->filters);
        if ($productStats->count() > 0) {
            $data[] = ['TOP PRODUCTS ANALYSIS'];
            $data[] = ['Product', 'Category', 'Orders', 'Total Quantity', 'Total Value', 'Suppliers', 'Avg Unit Price'];
            $topProducts = $productStats->take(10);
            foreach ($topProducts as $product) {
                $data[] = [
                    $product->product_name . ' (' . $product->product_code . ')',
                    $product->category_name,
                    number_format($product->total_pos),
                    number_format($product->total_quantity),
                    'Rp ' . number_format($product->total_po_amount, 0, ',', '.'),
                    number_format($product->unique_suppliers),
                    'Rp ' . number_format($product->average_unit_price, 0, ',', '.')
                ];
            }
            $data[] = [''];
        }

        // Monthly Trends
        $monthlyTrends = POReportSupplierProduct::getFilteredMonthlyTrends($this->filters);
        if ($monthlyTrends->count() > 0) {
            $data[] = ['MONTHLY TRENDS'];
            $data[] = ['Period', 'Orders', 'Total Value', 'Quantity', 'Suppliers', 'Products', 'Payment Rate'];

            $trendsToShow = $monthlyTrends;
            foreach ($trendsToShow as $trend) {
                $data[] = [
                    $trend->period_name,
                    number_format($trend->total_pos),
                    'Rp ' . number_format($trend->total_po_amount, 0, ',', '.'),
                    number_format($trend->total_quantity),
                    number_format($trend->unique_suppliers),
                    number_format($trend->unique_products),
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
                    'startColor' => ['rgb' => '7C3AED']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER
                ]
            ],
            // Style for section headers
            'A8' => ['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1F2937']]],
            'A16' => ['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1F2937']]],
            'A21' => ['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1F2937']]],
            'A33' => ['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1F2937']]],
            // Header rows styling
            9 => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']]],
            17 => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']]],
            22 => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']]],
            34 => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']]],
        ];
    }

    public function title(): string
    {
        return 'Summary';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 20,
            'C' => 20,
            'D' => 20,
            'E' => 20,
            'F' => 15,
            'G' => 20,
        ];
    }
}
