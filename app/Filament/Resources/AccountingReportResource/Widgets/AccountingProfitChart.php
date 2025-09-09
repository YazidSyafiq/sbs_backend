<?php

namespace App\Filament\Resources\AccountingReportResource\Widgets;

use App\Models\AccountingReport;
use Filament\Widgets\ChartWidget;

class AccountingProfitChart extends ChartWidget
{
    protected static ?string $heading = 'Financial Trends';
    protected static ?string $maxHeight = '500px';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $filters = session('accounting_filters', []);
        $monthlyData = AccountingReport::getMonthlyProfitTrends($filters);

        $labels = $monthlyData->pluck('period_name')->toArray();
        $revenues = $monthlyData->pluck('total_revenue')->map(fn($amount) => round($amount / 1000000, 2))->toArray();
        $costs = $monthlyData->pluck('total_cost')->map(fn($amount) => round($amount / 1000000, 2))->toArray();
        $profits = $monthlyData->pluck('gross_profit')->map(fn($amount) => round($amount / 1000000, 2))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Total Revenue',
                    'data' => $revenues,
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
                    'label' => 'Total Cost',
                    'data' => $costs,
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
                [
                    'label' => 'Gross Profit',
                    'data' => $profits,
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
        $filters = session('accounting_filters', []);
        return 'Financial Trends (' . AccountingReport::getPeriodLabel($filters) . ')';
    }

    public function getDescription(): ?string
    {
        return 'Blue line shows total revenue, red shows total cost, green shows gross profit (amounts in millions)';
    }
}
