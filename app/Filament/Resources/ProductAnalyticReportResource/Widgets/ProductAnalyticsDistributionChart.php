<?php

namespace App\Filament\Resources\ProductAnalyticReportResource\Widgets;

use App\Models\ProductAnalyticReport;
use Filament\Widgets\ChartWidget;

class ProductAnalyticsDistributionChart extends ChartWidget
{
    protected static ?string $heading = 'Product PO Status Breakdown';
    protected static ?string $maxHeight = '600px';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $filters = session('product_analytics_filters', []);
        $products = ProductAnalyticReport::getPOStatusDistribution($filters, 8);

        if ($products->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'label' => 'No Data',
                        'data' => [0],
                        'backgroundColor' => 'rgba(156, 163, 175, 0.8)',
                        'borderColor' => 'rgb(156, 163, 175)',
                        'borderWidth' => 2,
                    ]
                ],
                'labels' => ['No PO Data Available'],
            ];
        }

        $labels = $products->pluck('display_name')->toArray();

        // Data for each status
        $requestedData = $products->pluck('requested_qty')->toArray();
        $processingData = $products->pluck('processing_qty')->toArray();
        $shippedData = $products->pluck('shipped_qty')->toArray();
        $receivedData = $products->pluck('received_qty')->toArray();
        $doneData = $products->pluck('done_qty')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Requested',
                    'data' => $requestedData,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.8)',
                    'borderColor' => 'rgb(245, 158, 11)',
                    'borderWidth' => 1,
                    'borderRadius' => [
                        'topLeft' => 4,
                        'topRight' => 4,
                        'bottomLeft' => 0,
                        'bottomRight' => 0
                    ],
                ],
                [
                    'label' => 'Processing',
                    'data' => $processingData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 1,
                    'borderRadius' => 0,
                ],
                [
                    'label' => 'Shipped',
                    'data' => $shippedData,
                    'backgroundColor' => 'rgba(168, 85, 247, 0.8)',
                    'borderColor' => 'rgb(168, 85, 247)',
                    'borderWidth' => 1,
                    'borderRadius' => 0,
                ],
                [
                    'label' => 'Received',
                    'data' => $receivedData,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'borderWidth' => 1,
                    'borderRadius' => 0,
                ],
                [
                    'label' => 'Done',
                    'data' => $doneData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 1,
                    'borderRadius' => [
                        'topLeft' => 0,
                        'topRight' => 0,
                        'bottomLeft' => 4,
                        'bottomRight' => 4
                    ],
                ],
            ],
            'labels' => $labels,
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
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 15,
                        'font' => [
                            'size' => 11
                        ]
                    ]
                ],
                'tooltip' => [
                    'enabled' => true,
                    'backgroundColor' => 'rgba(0, 0, 0, 0.8)',
                    'titleColor' => '#ffffff',
                    'bodyColor' => '#ffffff',
                    'borderColor' => 'rgba(255, 255, 255, 0.1)',
                    'borderWidth' => 1,
                    'cornerRadius' => 6,
                    'padding' => 10,
                    'displayColors' => true,
                    'mode' => 'index',
                    'intersect' => false,
                ]
            ],
            'scales' => [
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Quantity',
                        'font' => [
                            'size' => 12,
                            'weight' => 'bold'
                        ]
                    ],
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.1)',
                    ],
                    'ticks' => [
                        'font' => [
                            'size' => 12
                        ]
                    ]
                ],
                'x' => [
                    'stacked' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Products',
                        'font' => [
                            'size' => 12,
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
                        'maxRotation' => 45,
                        'minRotation' => 0,
                    ]
                ],
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
        ];
    }
}
