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
                    'pointRadius' => 5,
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
                            'size' => 14,
                            'weight' => 'bold'
                        ]
                    ],
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.1)',
                    ],
                    'ticks' => [
                        'font' => [
                            'size' => 12
                        ],
                        'callback' => 'function(value) { return value + "%"; }'
                    ]
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Period',
                        'font' => [
                            'size' => 14,
                            'weight' => 'bold'
                        ]
                    ],
                    'grid' => [
                        'display' => false,
                    ],
                    'ticks' => [
                        'font' => [
                            'size' => 12
                        ]
                    ]
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
                        'padding' => 25,
                        'font' => [
                            'size' => 13,
                            'weight' => 'bold'
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
                    'padding' => 15,
                    'displayColors' => true,
                    'titleFont' => [
                        'size' => 14,
                        'weight' => 'bold'
                    ],
                    'bodyFont' => [
                        'size' => 13
                    ],
                    'callbacks' => [
                        'label' => 'function(context) {
                            return context.dataset.label + ": " + context.parsed.y + "%";
                        }'
                    ]
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
