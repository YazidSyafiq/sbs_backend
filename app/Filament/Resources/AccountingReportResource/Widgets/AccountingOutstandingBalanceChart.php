<?php

namespace App\Filament\Resources\AccountingReportResource\Widgets;

use App\Models\AccountingReport;
use Filament\Widgets\ChartWidget;

class AccountingOutstandingBalanceChart extends ChartWidget
{
    protected static ?string $heading = 'Outstanding Balance Analysis';
    protected static ?string $maxHeight = '400px';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $filters = session('accounting_filters', []);
        $debtAnalysis = AccountingReport::getDebtAnalysis($filters);

        return [
            'datasets' => [
                [
                    'label' => 'Outstanding Balance',
                    'data' => [
                        $debtAnalysis->receivables_from_customers / 1000000,
                        $debtAnalysis->debt_to_suppliers / 1000000,
                        abs($debtAnalysis->net_debt_position) / 1000000,
                    ],
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',  // Receivables - Blue
                        'rgba(245, 158, 11, 0.8)',  // Debt to Suppliers - Orange
                        $debtAnalysis->net_debt_position > 0 ? 'rgba(239, 68, 68, 0.8)' : 'rgba(34, 197, 94, 0.8)', // Net position
                    ],
                    'borderColor' => [
                        'rgb(59, 130, 246)',
                        'rgb(245, 158, 11)',
                        $debtAnalysis->net_debt_position > 0 ? 'rgb(239, 68, 68)' : 'rgb(34, 197, 94)',
                    ],
                    'borderWidth' => 2,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => [
                'Receivables (We\'re Owed)',
                'Payables (We Owe)',
                $debtAnalysis->net_debt_position > 0 ? 'Net Debt' : 'Net Credit'
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Amount (Million Rp)',
                        'font' => [
                            'size' => 12,
                            'weight' => 'bold'
                        ]
                    ],
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.1)',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Balance Categories',
                        'font' => [
                            'size' => 12,
                            'weight' => 'bold'
                        ]
                    ],
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false
                ],
                'tooltip' => [
                    'enabled' => true,
                    'backgroundColor' => 'rgba(0, 0, 0, 0.9)',
                    'titleColor' => '#fff',
                    'bodyColor' => '#fff',
                    'borderColor' => 'rgba(255, 255, 255, 0.3)',
                    'borderWidth' => 1,
                    'cornerRadius' => 8,
                    'padding' => 12,
                    'titleFont' => [
                        'size' => 14,
                        'weight' => 'bold'
                    ],
                    'bodyFont' => [
                        'size' => 13
                    ],
                ],
            ],
            'elements' => [
                'bar' => [
                    'borderWidth' => 2,
                ]
            ],
        ];
    }

    public function getDescription(): ?string
    {
        return 'Outstanding receivables and payables - money still owed to/from us';
    }
}
