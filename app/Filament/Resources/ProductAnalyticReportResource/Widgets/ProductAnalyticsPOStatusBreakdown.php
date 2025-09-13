<?php

namespace App\Filament\Resources\ProductAnalyticReportResource\Widgets;

use App\Models\ProductAnalyticReport;
use Filament\Widgets\ChartWidget;

class ProductAnalyticsPOStatusBreakdown extends ChartWidget
{
    protected static ?string $heading = 'Product PO Status Breakdown';
    protected static ?string $maxHeight = '500px';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $filters = session('product_analytics_filters', []);
        $products = ProductAnalyticReport::getProductPOStatusBreakdown($filters, 8);

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

        return [
            'datasets' => [
                [
                    'label' => 'Requested',
                    'data' => $products->pluck('requested')->toArray(),
                    'backgroundColor' => 'rgba(245, 158, 11, 0.8)',
                    'borderColor' => 'rgb(245, 158, 11)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Processing',
                    'data' => $products->pluck('processing')->toArray(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Shipped',
                    'data' => $products->pluck('shipped')->toArray(),
                    'backgroundColor' => 'rgba(168, 85, 247, 0.8)',
                    'borderColor' => 'rgb(168, 85, 247)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Received',
                    'data' => $products->pluck('received')->toArray(),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Done',
                    'data' => $products->pluck('done')->toArray(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 1,
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
                    ]
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Quantity',
                    ],
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.1)',
                    ],
                ],
                'x' => [
                    'stacked' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Products',
                    ],
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.1)',
                    ],
                    'ticks' => [
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

    public function getDescription(): ?string
    {
        return 'Shows distribution of products across different Purchase Order stages';
    }
}
