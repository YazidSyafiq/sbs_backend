<?php

namespace App\Exports;

use App\Models\POReportService;
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

class POServiceDetailSheet implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnWidths
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = POReportService::with(['user.branch', 'items.service', 'items.technician'])
            ->select([
                'service_purchases.*',
                'users.name as user_name',
                'branches.name as branch_name',
                'branches.code as branch_code',
            ])
            ->leftJoin('users', 'service_purchases.user_id', '=', 'users.id')
            ->leftJoin('branches', 'users.branch_id', '=', 'branches.id')
            ->activeOnly();

        // Apply filters
        $query = POReportService::applyFiltersToQuery($query, $this->filters);

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
            'Expected Process Date',
            'Total Amount',
            'Outstanding',
            'Paid Amount',
            'Services Count',
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
            ]);

            // Add technician columns at the end
            array_splice($basicHeadings, -2, 0, [
                'Technicians Involved',
                'Outstanding Debt to Techs',
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
            $po->expected_proccess_date ? $po->expected_proccess_date->format('d/m/Y') : '',
            $po->total_amount,
            $outstandingAmount,
            $paidAmount,
            $po->items->count(),
            $po->user_name ?? '',
            $invoiceUrl,
            $fakturUrl,
        ];

        // Add profit data ONLY for non-User roles AND only for PAID orders
        if ($user && !$user->hasRole('User')) {
            if ($po->status_paid === 'paid') {
                // Calculate profit data for PAID orders
                $totalCost = $po->items->sum('cost_price');
                $totalRevenue = $po->items->sum('selling_price');
                $totalProfit = $totalRevenue - $totalCost;
                $profitMargin = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0;

                // Insert profit data before last 5 elements
                array_splice($basicData, -5, 0, [
                    $totalCost,
                    $totalProfit,
                    $profitMargin,
                ]);
            } else {
                // For UNPAID orders, show zero/blank profit data
                array_splice($basicData, -5, 0, [
                    0, // Total Cost
                    0, // Total Profit
                    0, // Profit Margin
                ]);
            }

            // Add technician data
            $technicians = $po->items->pluck('technician.name')->filter()->unique()->implode(', ');
            $technicianDebt = 0;
            if ($po->type_po === 'credit' && $po->status_paid === 'unpaid') {
                $technicianDebt = $po->items->sum('cost_price');
            }

            // Insert technician data before last 2 elements
            array_splice($basicData, -2, 0, [
                $technicians ?: 'Not Assigned',
                $technicianDebt,
            ]);
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
        return URL::route('purchase-service.invoice', ['purchaseService' => $po->id]);
    }

    /**
     * Generate faktur URL
     */
    private function generateFakturUrl($po): string
    {
        if (!$po->id) {
            return '';
        }
        return URL::route('purchase-service.faktur', ['purchaseService' => $po->id]);
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
            // Technician debt formatting
            $styles['P:P'] = [
                'numberFormat' => [
                    'formatCode' => '#,##0'
                ]
            ];
            // Make Invoice and Faktur columns clickable (URL style)
            $styles['R:S'] = [
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
            $styles['N:O'] = [
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
            'H' => 12, // Expected Process Date
            'I' => 15, // Total Amount
            'J' => 15, // Outstanding
            'K' => 15, // Paid Amount
            'L' => 12, // Services Count
            'M' => 20, // Requested By
            'N' => 50, // Invoice URL
            'O' => 50, // Faktur URL
        ];

        if ($user && !$user->hasRole('User')) {
            // Add profit and technician column widths
            $basicWidths['M'] = 15; // Total Cost
            $basicWidths['N'] = 15; // Total Profit
            $basicWidths['O'] = 12; // Profit Margin %
            $basicWidths['P'] = 25; // Technicians Involved
            $basicWidths['Q'] = 15; // Outstanding Debt to Techs
            $basicWidths['R'] = 20; // Requested By
            $basicWidths['S'] = 50; // Invoice URL
            $basicWidths['T'] = 50; // Faktur URL
        }

        return $basicWidths;
    }
}
