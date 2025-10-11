<?php

namespace App\Exports;

use App\Models\POReportSupplierProduct;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Facades\URL;

class POSupplierProductDetailSheet implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnWidths
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = POReportSupplierProduct::with(['supplier', 'product', 'product.category', 'user'])
            ->select([
                'purchase_product_suppliers.*',
                'suppliers.name as supplier_name',
                'suppliers.code as supplier_code',
                'products.name as product_name',
                'products.code as product_code',
                'product_categories.name as category_name',
                'users.name as user_name',
            ])
            ->leftJoin('suppliers', 'purchase_product_suppliers.supplier_id', '=', 'suppliers.id')
            ->leftJoin('products', 'purchase_product_suppliers.product_id', '=', 'products.id')
            ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->leftJoin('users', 'purchase_product_suppliers.user_id', '=', 'users.id')
            ->activeOnly();

        // Apply filters
        $query = POReportSupplierProduct::applyFiltersToQuery($query, $this->filters);

        return $query->orderBy('order_date', 'desc');
    }

    public function headings(): array
    {
        return [
            'PO Number',
            'PO Name',
            'Supplier',
            'Supplier Code',
            'Product',
            'Product Code',
            'Category',
            'Quantity',
            'Unit Price',
            'Total Amount',
            'Type',
            'Status',
            'Payment Status',
            'Order Date',
            'Goods Received Date',
            'Outstanding Payment',
            'Amount Paid',
            'Requested By',
            'Faktur URL',
        ];
    }

    public function map($po): array
    {
        $outstandingAmount = $po->status_paid === 'unpaid' ? $po->total_amount : 0;
        $paidAmount = $po->status_paid === 'paid' ? $po->total_amount : 0;

        // Generate faktur URL
        $fakturUrl = $this->generateFakturUrl($po);

        return [
            $po->po_number,
            $po->name,
            $po->supplier_name ?? 'Unknown Supplier',
            $po->supplier_code ?? 'N/A',
            $po->product_name ?? 'Unknown Product',
            $po->product_code ?? 'N/A',
            $po->category_name ?? 'No Category',
            number_format($po->quantity),
            $po->unit_price,
            $po->total_amount,
            ucfirst($po->type_po ?? ''),
            $po->status,
            ucfirst($po->status_paid ?? 'Pending'),
            $po->order_date ? $po->order_date->format('d/m/Y') : '',
            $po->received_date ? $po->received_date->format('d/m/Y') : '',
            $outstandingAmount,
            $paidAmount,
            $po->user_name ?? '',
            $fakturUrl,
        ];
    }

    /**
     * Generate faktur URL
     */
    private function generateFakturUrl($po): string
    {
        if (!$po->id) {
            return '';
        }

        // Generate full URL for supplier faktur
        return URL::route('purchase-product-supplier.faktur', ['purchaseProduct' => $po->id]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '059669']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER
                ]
            ],
            // Format currency columns (Unit Price, Total Amount, Outstanding, Paid Amount)
            'I:J' => [
                'numberFormat' => [
                    'formatCode' => '#,##0'
                ]
            ],
            'P:Q' => [
                'numberFormat' => [
                    'formatCode' => '#,##0'
                ]
            ],
            // Make Faktur URL column clickable (URL style)
            'S:S' => [
                'font' => [
                    'color' => ['rgb' => '0000FF'],
                    'underline' => true
                ]
            ]
        ];
    }

    public function title(): string
    {
        return 'Detailed Data';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // PO Number
            'B' => 25, // PO Name
            'C' => 20, // Supplier
            'D' => 12, // Supplier Code
            'E' => 20, // Product
            'F' => 12, // Product Code
            'G' => 15, // Category
            'H' => 10, // Quantity
            'I' => 12, // Unit Price
            'J' => 15, // Total Amount
            'K' => 10, // Type
            'L' => 12, // Status
            'M' => 15, // Payment Status
            'N' => 12, // Order Date
            'O' => 12, // Received Date
            'P' => 15, // Outstanding
            'Q' => 15, // Paid Amount
            'R' => 20, // Requested By
            'S' => 50, // Faktur URL
        ];
    }
}
