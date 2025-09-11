<?php

namespace App\Exports;

use App\Models\AccountingReport;
use App\Models\PurchaseProductSupplier;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Facades\URL;

class SupplierItemsDetailSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnWidths
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        // Default to last 12 months if no filters
        if (empty($this->filters['date_from']) && empty($this->filters['date_until'])) {
            $dateFrom = now()->subMonths(11)->startOfMonth()->toDateString();
            $dateTo = now()->endOfMonth()->toDateString();
        } else {
            $dateFrom = !empty($this->filters['date_from']) ? $this->filters['date_from'] : now()->subMonths(11)->startOfMonth()->toDateString();
            $dateTo = !empty($this->filters['date_until']) ? $this->filters['date_until'] : now()->endOfMonth()->toDateString();
        }

        // Get Supplier PO items with all details
        return PurchaseProductSupplier::with(['product.category', 'supplier', 'user'])
            ->whereNotIn('status', ['Draft', 'Cancelled'])
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->orderBy('order_date', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Date',
            'PO Number',
            'PO Name',
            'User',
            'PO Status',
            'Payment Status',
            'PO Type',
            'Supplier',
            'Supplier Code',
            'Product',
            'Product Code',
            'Category',
            'Quantity',
            'Unit Price',
            'Total Cost',
            'Outstanding Payment',
            'Amount Paid',
            'Received Date',
            'Faktur URL',
        ];
    }

    public function map($po): array
    {
        $outstandingAmount = $po->status_paid === 'unpaid' ? $po->total_amount : 0;
        $paidAmount = $po->status_paid === 'paid' ? $po->total_amount : 0;

        // Generate faktur URL
        $fakturUrl = URL::route('purchase-product-supplier.faktur', ['purchaseProduct' => $po->id]);

        return [
            $po->order_date ? $po->order_date->format('d/m/Y') : '',
            $po->po_number,
            $po->name,
            $po->user->name ?? 'Unknown User',
            $po->status,
            ucfirst($po->status_paid ?? 'Pending'),
            ucfirst($po->type_po ?? ''),
            $po->supplier->name ?? 'Unknown Supplier',
            $po->supplier->code ?? 'N/A',
            $po->product->name ?? 'Unknown Product',
            $po->product->code ?? 'N/A',
            $po->product->category->name ?? 'No Category',
            $po->quantity,
            $po->unit_price,
            $po->total_amount,
            $outstandingAmount,
            $paidAmount,
            $po->received_date ? $po->received_date->format('d/m/Y') : '',
            $fakturUrl,
        ];
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
                    'startColor' => ['rgb' => 'DC2626']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER
                ]
            ],
            // Format currency columns
            'N:Q' => [
                'numberFormat' => [
                    'formatCode' => '#,##0'
                ]
            ],
            // URL styling
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
        return 'Supplier Items Detail';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12, // Date
            'B' => 20, // PO Number
            'C' => 25, // PO Name
            'D' => 15, // User
            'E' => 12, // PO Status
            'F' => 15, // Payment Status
            'G' => 10, // PO Type
            'H' => 20, // Supplier
            'I' => 12, // Supplier Code
            'J' => 20, // Product
            'K' => 12, // Product Code
            'L' => 15, // Category
            'M' => 10, // Quantity
            'N' => 12, // Unit Price
            'O' => 15, // Total Cost
            'P' => 15, // Outstanding Payment
            'Q' => 15, // Amount Paid
            'R' => 12, // Received Date
            'S' => 50, // Faktur URL
        ];
    }
}
