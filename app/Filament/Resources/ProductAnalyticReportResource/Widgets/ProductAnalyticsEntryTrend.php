<?php

namespace App\Filament\Resources\ProductAnalyticReportResource\Widgets;

use App\Models\ProductAnalyticReport;
use Filament\Widgets\ChartWidget;

class ProductAnalyticsEntryTrend extends ChartWidget
{
    protected static ?string $heading = 'Product Stock Received Trend Per Product (Last 30 Days)';
    protected static ?string $maxHeight = '600px';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $filters = session('product_analytics_filters', []);
        $trendData = ProductAnalyticReport::getProductEntryTrendPerProduct($filters, 30, 10);

        if (empty($trendData->products)) {
            return [
                'datasets' => [
                    [
                        'label' => 'No Data',
                        'data' => [0],
                        'borderColor' => 'rgba(156, 163, 175, 1)',
                        'backgroundColor' => 'rgba(156, 163, 175, 0.1)',
                        'tension' => 0.4,
                    ]
                ],
                'labels' => ['No Data Available'],
            ];
        }

        $datasets = [];
        $colors = [
            'rgba(59, 130, 246, 1)', // Blue
            'rgba(16, 185, 129, 1)', // Green
            'rgba(245, 158, 11, 1)', // Orange
            'rgba(239, 68, 68, 1)', // Red
            'rgba(168, 85, 247, 1)', // Purple
            'rgba(236, 72, 153, 1)', // Pink
            'rgba(14, 165, 233, 1)', // Sky
            'rgba(34, 197, 94, 1)', // Emerald
            'rgba(249, 115, 22, 1)', // Orange
            'rgba(99, 102, 241, 1)', // Indigo
        ];

        foreach ($trendData->products as $index => $product) {
            $colorIndex = $index % count($colors);
            $borderColor = $colors[$colorIndex];
            $backgroundColor = str_replace('1)', '0.1)', $borderColor);

            $datasets[] = [
                'label' => $product['display_name'],
                'data' => $product['data'],
                'borderColor' => $borderColor,
                'backgroundColor' => $backgroundColor,
                'fill' => false,
                'tension' => 0.4,
                'pointBackgroundColor' => $borderColor,
                'pointBorderColor' => $borderColor,
                'pointRadius' => 3,
                'pointHoverRadius' => 6,
                'pointHoverBackgroundColor' => $borderColor,
                'pointHoverBorderColor' => '#ffffff',
                'pointHoverBorderWidth' => 2,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $trendData->labels,
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
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 10,
                        'font' => [
                            'size' => 11
                        ]
                    ]
                ],
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false,
                    'backgroundColor' => 'rgba(0, 0, 0, 0.8)',
                    'titleColor' => '#ffffff',
                    'bodyColor' => '#ffffff',
                    'borderColor' => 'rgba(255, 255, 255, 0.2)',
                    'borderWidth' => 1,
                    'cornerRadius' => 8,
                    'padding' => 12,
                    'displayColors' => true,
                    'titleFont' => [
                        'size' => 13,
                        'weight' => 'bold'
                    ],
                    'bodyFont' => [
                        'size' => 12
                    ]
                ]
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Quantity Received',
                        'font' => [
                            'size' => 12,
                            'weight' => 'bold'
                        ]
                    ],
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.1)',
                        'borderDash' => [5, 5],
                    ],
                    'ticks' => [
                        'font' => [
                            'size' => 11
                        ],
                        'stepSize' => 1,
                        'precision' => 0
                    ]
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Date',
                        'font' => [
                            'size' => 12,
                            'weight' => 'bold'
                        ]
                    ],
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.05)',
                    ],
                    'ticks' => [
                        'font' => [
                            'size' => 11
                        ],
                        'maxRotation' => 45,
                        'minRotation' => 0,
                    ]
                ],
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
            'elements' => [
                'line' => [
                    'borderWidth' => 2,
                ],
                'point' => [
                    'borderWidth' => 2,
                    'hoverRadius' => 6,
                ]
            ]
        ];
    }
}
