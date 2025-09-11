<?php

namespace App\Exports;

use App\Models\AccountingReport;
use App\Models\ServicePurchase;
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
use Auth;

class ServiceItemsDetailSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnWidths
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

        $items = collect();

        // Get Service PO items with all details
        $servicePos = ServicePurchase::with(['items.service.category', 'items.technician', 'user.branch'])
            ->whereNotIn('status', ['Draft', 'Cancelled'])
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->orderBy('order_date', 'desc')
            ->get();

        foreach ($servicePos as $po) {
            foreach ($po->items as $item) {
                $items->push([
                    'po' => $po,
                    'item' => $item,
                    'service' => $item->service,
                    'technician' => $item->technician,
                    'category' => $item->service->category ?? null,
                ]);
            }
        }

        return $items;
    }

    public function headings(): array
    {
        $user = Auth::user();

        $basicHeadings = [
            'Date',
            'PO Number',
            'PO Name',
            'Branch',
            'User',
            'PO Status',
            'Payment Status',
            'PO Type',
            'Service Name',
            'Service Code',
            'Category',
            'Technician',
            'Selling Price',
            'Total Revenue',
            'Outstanding Amount',
            'Invoice URL',
            'Faktur URL',
        ];

        // Add profit columns ONLY for non-User roles
        if ($user && !$user->hasRole('User')) {
            // Insert profit columns before URLs
            array_splice($basicHeadings, -2, 0, [
                'Technician Cost',
                'Service Profit',
                'Profit Margin %',
                'Outstanding Debt to Tech'
            ]);
        }

        return $basicHeadings;
    }

    public function map($data): array
    {
        $user = Auth::user();
        $po = $data['po'];
        $item = $data['item'];
        $service = $data['service'];
        $technician = $data['technician'];
        $category = $data['category'];

        // Calculate values based on payment status
        $totalRevenue = $po->status_paid === 'paid' ? $item->selling_price : 0;
        $outstandingAmount = $po->status_paid === 'unpaid' ? $item->selling_price : 0;

        // Generate URLs
        $invoiceUrl = URL::route('purchase-service.invoice', ['purchaseService' => $po->id]);
        $fakturUrl = URL::route('purchase-service.faktur', ['purchaseService' => $po->id]);

        $basicData = [
            $po->order_date ? $po->order_date->format('d/m/Y') : '',
            $po->po_number,
            $po->name,
            $po->user->branch->name ?? 'No Branch',
            $po->user->name ?? 'Unknown User',
            $po->status,
            ucfirst($po->status_paid ?? 'Pending'),
            ucfirst($po->type_po ?? ''),
            $service->name ?? 'Unknown Service',
            $service->code ?? 'N/A',
            $category->name ?? 'No Category',
            $technician->name ?? 'Not Assigned',
            $item->selling_price,
            $totalRevenue,
            $outstandingAmount,
            $invoiceUrl,
            $fakturUrl,
        ];

        // Add profit data ONLY for non-User roles
        if ($user && !$user->hasRole('User')) {
            $technicianCost = $po->status_paid === 'paid' ? $item->cost_price : 0;
            $serviceProfit = $totalRevenue - $technicianCost;
            $profitMargin = $totalRevenue > 0 ? round(($serviceProfit / $totalRevenue) * 100, 2) : 0;

            // Outstanding debt to technician (for credit PO that are unpaid)
            $outstandingDebtToTech = 0;
            if ($po->type_po === 'credit' && $po->status_paid === 'unpaid') {
                $outstandingDebtToTech = $item->cost_price;
            }

            // Insert profit data before last 2 elements
            array_splice($basicData, -2, 0, [
                $item->cost_price,
                $serviceProfit,
                $profitMargin,
                $outstandingDebtToTech
            ]);
        }

        return $basicData;
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
                    'startColor' => ['rgb' => '7C3AED']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER
                ]
            ],
        ];

        if ($user && !$user->hasRole('User')) {
            // Format with profit columns
            $styles['M:R'] = [
                'numberFormat' => [
                    'formatCode' => '#,##0'
                ]
            ];
            // Format percentage column
            $styles['Q:Q'] = [
                'numberFormat' => [
                    'formatCode' => '0.00"%"'
                ]
            ];
            // URL styling
            $styles['S:T'] = [
                'font' => [
                    'color' => ['rgb' => '0000FF'],
                    'underline' => true
                ]
            ];
        } else {
            // Format without profit columns
            $styles['M:O'] = [
                'numberFormat' => [
                    'formatCode' => '#,##0'
                ]
            ];
            // URL styling
            $styles['P:Q'] = [
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
        return 'Service Items Detail';
    }

    public function columnWidths(): array
    {
        $user = Auth::user();

        $basicWidths = [
            'A' => 12, // Date
            'B' => 20, // PO Number
            'C' => 25, // PO Name
            'D' => 15, // Branch
            'E' => 15, // User
            'F' => 12, // PO Status
            'G' => 15, // Payment Status
            'H' => 10, // PO Type
            'I' => 25, // Service Name
            'J' => 12, // Service Code
            'K' => 15, // Category
            'L' => 20, // Technician
            'M' => 12, // Selling Price
            'N' => 15, // Total Revenue
            'O' => 15, // Outstanding Amount
            'P' => 40, // Invoice URL
            'Q' => 40, // Faktur URL
        ];

        if ($user && !$user->hasRole('User')) {
            // Add profit column widths
            $basicWidths['P'] = 12; // Technician Cost
            $basicWidths['Q'] = 12; // Service Profit
            $basicWidths['R'] = 12; // Profit Margin %
            $basicWidths['S'] = 15; // Outstanding Debt to Tech
            $basicWidths['T'] = 40; // Invoice URL
            $basicWidths['U'] = 40; // Faktur URL
        }

        return $basicWidths;
    }
}
