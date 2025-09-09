<?php

namespace App\Filament\Resources\AccountingReportResource\Widgets;

use App\Models\AccountingReport;
use Filament\Widgets\ChartWidget;

class AccountingActualCashFlowChart extends ChartWidget
{
    protected static ?string $heading = 'Actual Cash Flow Analysis';
    protected static ?string $maxHeight = '400px';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $filters = session('accounting_filters', []);
        $cashFlow = AccountingReport::getCashFlowAnalysis($filters);

        return [
            'datasets' => [
                [
                    'label' => 'Actual Cash Flow',
                    'data' => [
                        $cashFlow->actual_cash_in / 1000000,
                        $cashFlow->actual_cash_out / 1000000,
                        $cashFlow->net_actual_cash_flow / 1000000,
                    ],
                    'backgroundColor' => [
                        'rgba(34, 197, 94, 0.8)',   // Actual Cash In - Green
                        'rgba(239, 68, 68, 0.8)',   // Actual Cash Out - Red
                        $cashFlow->net_actual_cash_flow >= 0 ? 'rgba(34, 197, 94, 1)' : 'rgba(239, 68, 68, 1)', // Net
                    ],
                    'borderColor' => [
                        'rgb(34, 197, 94)',
                        'rgb(239, 68, 68)',
                        $cashFlow->net_actual_cash_flow >= 0 ? 'rgb(34, 197, 94)' : 'rgb(239, 68, 68)',
                    ],
                    'borderWidth' => 2,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => [
                'Actual Cash In',
                'Actual Cash Out',
                'Net Cash Flow'
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
                        'text' => 'Cash Flow Categories',
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
        return 'Cash that actually flowed in and out during the selected period';
    }
}
