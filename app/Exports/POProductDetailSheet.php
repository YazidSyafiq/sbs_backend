<?php

namespace App\Exports;

use App\Models\POReportProduct;
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

class POProductDetailSheet implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnWidths
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = POReportProduct::with(['user.branch'])
            ->select([
                'purchase_products.*',
                'users.name as user_name',
                'branches.name as branch_name',
                'branches.code as branch_code',
            ])
            ->leftJoin('users', 'purchase_products.user_id', '=', 'users.id')
            ->leftJoin('branches', 'users.branch_id', '=', 'branches.id')
            ->activeOnly();

        // Apply filters
        $query = POReportProduct::applyFiltersToQuery($query, $this->filters);

        return $query->orderBy('order_date', 'desc');
    }

    public function headings(): array
    {
        return [
            'PO Number',
            'PO Name',
            'Branch',
            'Type',
            'Status',
            'Payment Status',
            'Order Date',
            'Expected Delivery',
            'Total Amount',
            'Outstanding',
            'Paid Amount',
            'Requested By',
            'Invoice',
            'Faktur',
        ];
    }

    public function map($po): array
    {
        $outstandingAmount = $po->status_paid === 'unpaid' ? $po->total_amount : 0;
        $paidAmount = $po->status_paid === 'paid' ? $po->total_amount : 0;

        // Generate invoice URL - same for both invoice and faktur
        $invoiceUrl = $this->generateInvoiceUrl($po);
        $fakturUrl = $this->generateFakturUrl($po); // Same implementation

        return [
            $po->po_number,
            $po->name,
            $po->branch_name ?? 'No Branch',
            ucfirst($po->type_po ?? ''),
            $po->status,
            ucfirst($po->status_paid ?? 'Pending'),
            $po->order_date ? $po->order_date->format('d/m/Y') : '',
            $po->expected_delivery_date ? $po->expected_delivery_date->format('d/m/Y') : '',
            $po->total_amount,
            $outstandingAmount,
            $paidAmount,
            $po->user_name ?? '',
            $invoiceUrl,
            $fakturUrl,
        ];
    }

    /**
     * Generate invoice URL
     */
    private function generateInvoiceUrl($po): string
    {
        if (!$po->id) {
            return '';
        }

        // Generate full URL for invoice
        return URL::route('purchase-product.invoice', ['purchaseProduct' => $po->id]);
    }

    /**
     * Generate faktur URL
     */
    private function generateFakturUrl($po): string
    {
        if (!$po->id) {
            return '';
        }

        // Generate full URL for invoice
        return URL::route('purchase-product.faktur', ['purchaseProduct' => $po->id]);
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
            // Format currency columns
            'I:K' => [
                'numberFormat' => [
                    'formatCode' => '#,##0'
                ]
            ],
            // Make Invoice and Faktur columns clickable (URL style)
            'M:N' => [
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
            'C' => 20, // Branch
            'D' => 12, // Type
            'E' => 12, // Status
            'F' => 15, // Payment Status
            'G' => 12, // Order Date
            'H' => 12, // Expected Delivery
            'I' => 15, // Total Amount
            'J' => 15, // Outstanding
            'K' => 15, // Paid Amount
            'L' => 20, // Requested By
            'M' => 50, // Invoice URL
            'N' => 50, // Faktur URL
        ];
    }
}
