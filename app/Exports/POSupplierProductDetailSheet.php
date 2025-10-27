<?php

namespace App\Exports;

use App\Models\POReportSupplierProduct;
use App\Models\PurchaseProductSupplier;
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
        $query = PurchaseProductSupplier::query()
            ->select([
                'purchase_product_suppliers.id',
                'purchase_product_suppliers.po_number',
                'purchase_product_suppliers.name',
                'purchase_product_suppliers.order_date',
                'purchase_product_suppliers.received_date',
                'purchase_product_suppliers.status',
                'purchase_product_suppliers.status_paid',
                'purchase_product_suppliers.type_po',
                'purchase_product_suppliers.user_id',
                'suppliers.name as supplier_name',
                'suppliers.code as supplier_code',
                'products.name as product_name',
                'products.code as product_code',
                'product_categories.name as category_name',
                'purchase_product_supplier_items.quantity',
                'purchase_product_supplier_items.unit_price',
                'purchase_product_supplier_items.total_price as total_amount',
                'users.name as user_name',
            ])
            ->leftJoin('suppliers', 'purchase_product_suppliers.supplier_id', '=', 'suppliers.id')
            ->leftJoin('purchase_product_supplier_items', function($join) {
                $join->on('purchase_product_suppliers.id', '=', 'purchase_product_supplier_items.purchase_product_supplier_id')
                     ->whereNull('purchase_product_supplier_items.deleted_at');
            })
            ->leftJoin('products', function($join) {
                $join->on('purchase_product_supplier_items.product_id', '=', 'products.id')
                     ->whereNull('products.deleted_at');
            })
            ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->leftJoin('users', 'purchase_product_suppliers.user_id', '=', 'users.id')
            ->whereNotIn('purchase_product_suppliers.status', ['Cancelled'])
            ->whereNull('purchase_product_suppliers.deleted_at');

        // Apply filters using POReportSupplierProduct helper
        if (!empty($this->filters['supplier_id'])) {
            $query->where('purchase_product_suppliers.supplier_id', $this->filters['supplier_id']);
        }

        if (!empty($this->filters['product_id'])) {
            $query->where('purchase_product_supplier_items.product_id', $this->filters['product_id']);
        }

        if (!empty($this->filters['category_id'])) {
            $query->where('product_categories.id', $this->filters['category_id']);
        }

        if (!empty($this->filters['type_po'])) {
            $query->whereIn('purchase_product_suppliers.type_po', $this->filters['type_po']);
        }

        if (!empty($this->filters['status'])) {
            $query->whereIn('purchase_product_suppliers.status', $this->filters['status']);
        }

        if (!empty($this->filters['status_paid'])) {
            $query->whereIn('purchase_product_suppliers.status_paid', $this->filters['status_paid']);
        }

        if (!empty($this->filters['date_from'])) {
            $query->whereDate('purchase_product_suppliers.order_date', '>=', $this->filters['date_from']);
        }

        if (!empty($this->filters['date_until'])) {
            $query->whereDate('purchase_product_suppliers.order_date', '<=', $this->filters['date_until']);
        }

        if (!empty($this->filters['outstanding_only'])) {
            $query->where('purchase_product_suppliers.status_paid', 'unpaid');
        }

        return $query->orderBy('purchase_product_suppliers.order_date', 'desc');
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
            $po->quantity ? number_format($po->quantity) : '0',
            $po->unit_price ?? 0,
            $po->total_amount ?? 0,
            ucfirst($po->type_po ?? ''),
            $po->status,
            ucfirst($po->status_paid ?? 'Pending'),
            $po->order_date ? \Carbon\Carbon::parse($po->order_date)->format('d/m/Y') : '',
            $po->received_date ? \Carbon\Carbon::parse($po->received_date)->format('d/m/Y') : '',
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
        try {
            return URL::route('purchase-product-supplier.faktur', ['purchaseProduct' => $po->id]);
        } catch (\Exception $e) {
            return '';
        }
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
