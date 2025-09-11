<?php

namespace App\Exports;

use App\Models\AccountingReport;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Auth;

class ComprehensiveTransactionDetailSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnWidths
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        return AccountingReport::getTransactionDetailsForExport($this->filters);
    }

    public function headings(): array
    {
        $user = Auth::user();

        $basicHeadings = [
            'Date',
            'Transaction Type',
            'PO Number',
            'Transaction Name',
            'Branch',
            'User',
            'Status',
            'Payment Status',
            'Item Type',
            'Item Name',
            'Item Code',
            'Category',
            'Quantity',
            'Unit Price',
            'Total Revenue',
            'Supplier/Technician',
            'Description'
        ];

        // Add profit columns ONLY for non-User roles
        if ($user && !$user->hasRole('User')) {
            // Insert profit columns before 'Supplier/Technician'
            array_splice($basicHeadings, -2, 0, [
                'Cost Price',
                'Profit Amount',
                'Profit Margin %'
            ]);
        }

        return $basicHeadings;
    }

    public function map($transaction): array
    {
        $user = Auth::user();

        $basicData = [
            $transaction['date'] instanceof \Carbon\Carbon ? $transaction['date']->format('d/m/Y') : $transaction['date'],
            $transaction['transaction_type'],
            $transaction['po_number'] ?? '',
            $transaction['transaction_name'],
            $transaction['branch'] ?? '',
            $transaction['user'] ?? '',
            $transaction['status'],
            $transaction['payment_status'],
            $transaction['item_type'],
            $transaction['item_name'],
            $transaction['item_code'] ?? '',
            $transaction['category'] ?? '',
            $transaction['quantity'],
            $transaction['unit_price'],
            $transaction['total_price'],
            $transaction['supplier_technician'] ?? '',
            $transaction['description'] ?? ''
        ];

        // Add profit data ONLY for non-User roles
        if ($user && !$user->hasRole('User')) {
            // Insert profit data before last 2 elements
            array_splice($basicData, -2, 0, [
                $transaction['cost_price'],
                $transaction['profit'],
                $transaction['profit_margin']
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
                    'startColor' => ['rgb' => '1F2937']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER
                ]
            ],
        ];

        if ($user && !$user->hasRole('User')) {
            // Format with profit columns
            $styles['N:R'] = [
                'numberFormat' => [
                    'formatCode' => '#,##0'
                ]
            ];
            // Format percentage column
            $styles['R:R'] = [
                'numberFormat' => [
                    'formatCode' => '0.00"%"'
                ]
            ];
        } else {
            // Format without profit columns
            $styles['N:O'] = [
                'numberFormat' => [
                    'formatCode' => '#,##0'
                ]
            ];
        }

        return $styles;
    }

    public function title(): string
    {
        return 'All Transactions';
    }

    public function columnWidths(): array
    {
        $user = Auth::user();

        $basicWidths = [
            'A' => 12, // Date
            'B' => 15, // Transaction Type
            'C' => 20, // PO Number
            'D' => 25, // Transaction Name
            'E' => 15, // Branch
            'F' => 15, // User
            'G' => 12, // Status
            'H' => 15, // Payment Status
            'I' => 12, // Item Type
            'J' => 25, // Item Name
            'K' => 12, // Item Code
            'L' => 15, // Category
            'M' => 10, // Quantity
            'N' => 15, // Unit Price
            'O' => 15, // Total Revenue
            'P' => 20, // Supplier/Technician
            'Q' => 30, // Description
        ];

        if ($user && !$user->hasRole('User')) {
            // Add profit column widths
            $basicWidths['P'] = 15; // Cost Price
            $basicWidths['Q'] = 15; // Profit Amount
            $basicWidths['R'] = 12; // Profit Margin %
            $basicWidths['S'] = 20; // Supplier/Technician
            $basicWidths['T'] = 30; // Description
        }

        return $basicWidths;
    }
}
