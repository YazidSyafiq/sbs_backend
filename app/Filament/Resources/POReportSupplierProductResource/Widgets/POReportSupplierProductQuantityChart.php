<?php

namespace App\Filament\Resources\POReportSupplierProductResource\Widgets;

use App\Models\POReportSupplierProduct;
use Filament\Widgets\ChartWidget;

class POReportSupplierProductQuantityChart extends ChartWidget
{
    protected static ?string $heading = 'Product Quantity Trends';
    protected static ?string $maxHeight = '600px';

    // Full width column span
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $filters = session('supplier_product_filters', []);

        $monthlyData = POReportSupplierProduct::getFilteredMonthlyTrends($filters);

        $labels = $monthlyData->pluck('period_name')->toArray();

        // Quantity data (actual numbers, no conversion)
        $quantities = $monthlyData->pluck('total_quantity')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Total Quantity Ordered',
                    'data' => $quantities,
                    'borderColor' => 'rgb(168, 85, 247)',
                    'backgroundColor' => 'rgba(168, 85, 247, 0.2)',
                    'fill' => true,
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
                        'text' => 'Quantity (Units)',
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
        $filters = session('supplier_product_filters', []);
        return 'Product Quantity Trends (' . POReportSupplierProduct::getPeriodLabel($filters) . ')';
    }
}
