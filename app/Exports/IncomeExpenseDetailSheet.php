<?php

namespace App\Exports;

use App\Models\AccountingReport;
use App\Models\Income;
use App\Models\Expense;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class IncomeExpenseDetailSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnWidths
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

        $transactions = collect();

        // Get Income transactions
        $incomes = Income::whereBetween('date', [$dateFrom, $dateTo])
            ->orderBy('date', 'desc')
            ->get();

        foreach ($incomes as $income) {
            $transactions->push([
                'type' => 'Income',
                'date' => $income->date,
                'name' => $income->name,
                'amount' => $income->income_amount,
                'description' => $income->description,
                'impact' => 'Positive',
                'created_at' => $income->created_at,
            ]);
        }

        // Get Expense transactions
        $expenses = Expense::whereBetween('date', [$dateFrom, $dateTo])
            ->orderBy('date', 'desc')
            ->get();

        foreach ($expenses as $expense) {
            $transactions->push([
                'type' => 'Expense',
                'date' => $expense->date,
                'name' => $expense->name,
                'amount' => $expense->expense_amount,
                'description' => $expense->description,
                'impact' => 'Negative',
                'created_at' => $expense->created_at,
            ]);
        }

        return $transactions->sortByDesc('date');
    }

    public function headings(): array
    {
        return [
            'Type',
            'Date',
            'Name',
            'Amount',
            'Impact',
            'Description',
            'Created At',
            'Net Effect',
            'Running Balance'
        ];
    }

    public function map($transaction): array
    {
        static $runningBalance = 0;

        // Calculate net effect and running balance
        $netEffect = $transaction['type'] === 'Income' ? $transaction['amount'] : -$transaction['amount'];
        $runningBalance += $netEffect;

        return [
            $transaction['type'],
            $transaction['date'] instanceof \Carbon\Carbon ? $transaction['date']->format('d/m/Y') : $transaction['date'],
            $transaction['name'],
            $transaction['amount'],
            $transaction['impact'],
            $transaction['description'] ?? '',
            $transaction['created_at'] ? $transaction['created_at']->format('d/m/Y H:i') : '',
            $netEffect,
            $runningBalance
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
                    'startColor' => ['rgb' => '2563EB']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER
                ]
            ],
            // Format currency columns
            'D:D' => [
                'numberFormat' => [
                    'formatCode' => '#,##0'
                ]
            ],
            'H:I' => [
                'numberFormat' => [
                    'formatCode' => '#,##0'
                ]
            ],
            // Conditional formatting based on impact
            'E:E' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER
                ]
            ]
        ];
    }

    public function title(): string
    {
        return 'Income & Expense Detail';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10, // Type
            'B' => 12, // Date
            'C' => 30, // Name
            'D' => 15, // Amount
            'E' => 10, // Impact
            'F' => 40, // Description
            'G' => 15, // Created At
            'H' => 15, // Net Effect
            'I' => 15, // Running Balance
        ];
    }
}
