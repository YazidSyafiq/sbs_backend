<?php

namespace App\Filament\Resources\POReportProductResource\Widgets;

use App\Models\POReportProduct;
use Filament\Widgets\ChartWidget;

class POReportProductProfitMarginChart extends ChartWidget
{
    protected static ?string $heading = 'Profit Margin Trends';
    protected static ?string $maxHeight = '400px';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $filters = session('po_product_filters', []);
        $profitData = POReportProduct::getFilteredProfitTrends($filters);

        $labels = $profitData->pluck('period_name')->toArray();
        $margins = $profitData->pluck('profit_margin')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Realized Profit Margin',
                    'data' => $margins,
                    'borderColor' => 'rgb(168, 85, 247)', // Purple
                    'backgroundColor' => 'rgba(168, 85, 247, 0.1)', // Light purple
                    'fill' => false,
                    'tension' => 0.4,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 8,
                    'pointBackgroundColor' => 'rgb(168, 85, 247)',
                    'pointBorderColor' => '#fff',
                    'pointBorderWidth' => 2,
                    'borderWidth' => 3,
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
                        'text' => 'Profit Margin (%)',
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
        $filters = session('po_product_filters', []);
        return 'Realized Profit Margin Performance (' . POReportProduct::getPeriodLabel($filters) . ')';
    }

    public function getDescription(): ?string
    {
        return 'Profit margin percentage trends from paid orders only';
    }
}
