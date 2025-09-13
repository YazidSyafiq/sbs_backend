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
use Auth;

class POProductDetailSheet implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnWidths
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = POReportProduct::with(['user.branch', 'items.product'])
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
        $user = Auth::user();

        $basicHeadings = [
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

        // Add profit columns ONLY for non-User roles
        if ($user && !$user->hasRole('User')) {
            // Insert profit columns before 'Requested By'
            array_splice($basicHeadings, -3, 0, [
                'Total Cost',
                'Realized Profit',
                'Profit Margin %',
                'Items Count'
            ]);
        }

        return $basicHeadings;
    }

    public function map($po): array
    {
        $user = Auth::user();

        $outstandingAmount = $po->status_paid === 'unpaid' ? $po->total_amount : 0;
        $paidAmount = $po->status_paid === 'paid' ? $po->total_amount : 0;

        // Generate URLs
        $invoiceUrl = $this->generateInvoiceUrl($po);
        $fakturUrl = $this->generateFakturUrl($po);

        $basicData = [
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

        // Add profit data ONLY for non-User roles AND only for PAID orders
        if ($user && !$user->hasRole('User')) {
            if ($po->status_paid === 'paid') {
                // Calculate profit data for PAID orders using cost_price from items
                $totalCost = $po->items->sum(function($item) {
                    return $item->cost_price ? ($item->cost_price * $item->quantity) : 0;
                });
                $totalRevenue = $po->total_amount;
                $totalProfit = $totalRevenue - $totalCost;
                $profitMargin = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0;
                $itemsCount = $po->items->sum('quantity');

                // Insert profit data before last 3 elements
                array_splice($basicData, -3, 0, [
                    $totalCost,
                    $totalProfit,
                    $profitMargin,
                    $itemsCount
                ]);
            } else {
                // For UNPAID orders, show zero/blank profit data
                array_splice($basicData, -3, 0, [
                    0, // Total Cost
                    0, // Total Profit
                    0, // Profit Margin
                    $po->items->sum('quantity') // Items Count (still show this)
                ]);
            }
        }

        return $basicData;
    }

    /**
     * Generate invoice URL
     */
    private function generateInvoiceUrl($po): string
    {
        if (!$po->id) {
            return '';
        }
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
        return URL::route('purchase-product.faktur', ['purchaseProduct' => $po->id]);
    }

    public function styles(Worksheet $sheet)
    {
        $user = Auth::user();

        $styles = [
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
        ];

        if ($user && !$user->hasRole('User')) {
            // Format with profit columns
            $styles['I:M'] = [
                'numberFormat' => [
                    'formatCode' => '#,##0'
                ]
            ];
            // Format percentage column
            $styles['N:N'] = [
                'numberFormat' => [
                    'formatCode' => '0.00"%"'
                ]
            ];
            // Items count formatting
            $styles['O:O'] = [
                'numberFormat' => [
                    'formatCode' => '#,##0'
                ]
            ];
            // Make Invoice and Faktur columns clickable (URL style)
            $styles['Q:R'] = [
                'font' => [
                    'color' => ['rgb' => '0000FF'],
                    'underline' => true
                ]
            ];
        } else {
            // Format without profit columns
            $styles['I:K'] = [
                'numberFormat' => [
                    'formatCode' => '#,##0'
                ]
            ];
            // Make Invoice and Faktur columns clickable (URL style)
            $styles['M:N'] = [
                'font' => [
                    'color' => ['rgb' => '0000FF'],
                    'underline' => true
                ]
            ];
        }

        return $styles;
    }

    public function title(): string
    {
        return 'Detailed Data';
    }

    public function columnWidths(): array
    {
        $user = Auth::user();

        $basicWidths = [
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

        if ($user && !$user->hasRole('User')) {
            // Add profit column widths
            $basicWidths['L'] = 15; // Total Cost
            $basicWidths['M'] = 15; // Total Profit
            $basicWidths['N'] = 12; // Profit Margin %
            $basicWidths['O'] = 12; // Items Count
            $basicWidths['P'] = 20; // Requested By
            $basicWidths['Q'] = 50; // Invoice URL
            $basicWidths['R'] = 50; // Faktur URL
        }

        return $basicWidths;
    }
}
