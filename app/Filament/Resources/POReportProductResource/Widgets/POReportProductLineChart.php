<?php

namespace App\Filament\Resources\POReportProductResource\Widgets;

use App\Models\POReportProduct;
use Filament\Widgets\ChartWidget;

class POReportProductLineChart extends ChartWidget
{
    protected static ?string $heading = 'Financial Trends';
    protected static ?string $maxHeight = '500px';

    // ADD THIS: Full width column span
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $filters = session('po_accounting_filters', []);

        $monthlyData = POReportProduct::getFilteredMonthlyTrends($filters);

        $labels = $monthlyData->pluck('period_name')->toArray();

        // Convert to millions for better readability
        $totalAmounts = $monthlyData->pluck('total_po_amount')->map(fn($amount) => round($amount / 1000000, 2))->toArray();
        $paidAmounts = $monthlyData->pluck('paid_amount')->map(fn($amount) => round($amount / 1000000, 2))->toArray();
        $outstandingAmounts = $monthlyData->pluck('outstanding_debt')->map(fn($amount) => round($amount / 1000000, 2))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Total PO Value',
                    'data' => $totalAmounts,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => false,
                    'tension' => 0.4,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 8,
                    'pointBackgroundColor' => 'rgb(59, 130, 246)',
                    'pointBorderColor' => '#fff',
                    'pointBorderWidth' => 2,
                    'borderWidth' => 3,
                ],
                [
                    'label' => 'Amount Received',
                    'data' => $paidAmounts,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.3)',
                    'fill' => 'origin',
                    'tension' => 0.4,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 8,
                    'pointBackgroundColor' => 'rgb(34, 197, 94)',
                    'pointBorderColor' => '#fff',
                    'pointBorderWidth' => 2,
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Outstanding Debt',
                    'data' => $outstandingAmounts,
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.3)',
                    'fill' => 'origin',
                    'tension' => 0.4,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 8,
                    'pointBackgroundColor' => 'rgb(239, 68, 68)',
                    'pointBorderColor' => '#fff',
                    'pointBorderWidth' => 2,
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'elements' => [
                'point' => [
                    'hoverRadius' => 8,
                ]
            ],
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
                        'text' => 'Period',
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
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 20,
                        'font' => [
                            'size' => 12
                        ]
                    ]
                ],
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false,
                    'backgroundColor' => 'rgba(0, 0, 0, 0.9)',
                    'titleColor' => '#fff',
                    'bodyColor' => '#fff',
                    'borderColor' => 'rgba(255, 255, 255, 0.3)',
                    'borderWidth' => 1,
                    'cornerRadius' => 8,
                    'padding' => 12,
                    'displayColors' => true,
                    'titleFont' => [
                        'size' => 14,
                        'weight' => 'bold'
                    ],
                    'bodyFont' => [
                        'size' => 13
                    ],
                ]
            ],
        ];
    }

    public function getHeading(): ?string
    {
        $filters = session('po_accounting_filters', []);
        return 'Financial Trends (' . POReportProduct::getPeriodLabel($filters) . ')';
    }

    public function getDescription(): ?string
    {
        return 'Blue line shows total PO value, green shows received amounts, red shows outstanding debts';
    }
}
