<?php

namespace App\Filament\Resources\POReportSupplierProductResource\Widgets;

use App\Models\POReportSupplierProduct;
use Filament\Widgets\ChartWidget;

class POReportSupplierProductDiversityChart extends ChartWidget
{
    protected static ?string $heading = 'Supplier & Product Diversity Trends';
    protected static ?string $maxHeight = '600px';

    // Full width column span
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $filters = session('supplier_product_filters', []);

        $monthlyData = POReportSupplierProduct::getFilteredMonthlyTrends($filters);

        $labels = $monthlyData->pluck('period_name')->toArray();
        $supplierCounts = $monthlyData->pluck('unique_suppliers')->toArray();
        $productCounts = $monthlyData->pluck('unique_products')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Unique Suppliers',
                    'data' => $supplierCounts,
                    'backgroundColor' => 'rgba(168, 85, 247, 0.6)',
                    'borderColor' => 'rgb(168, 85, 247)',
                    'borderWidth' => 2,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                ],
                [
                    'label' => 'Unique Products',
                    'data' => $productCounts,
                    'backgroundColor' => 'rgba(236, 72, 153, 0.6)',
                    'borderColor' => 'rgb(236, 72, 153)',
                    'borderWidth' => 2,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
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
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Number of Unique Suppliers/Products',
                        'font' => [
                            'size' => 12,
                            'weight' => 'bold'
                        ]
                    ],
                    'ticks' => [
                        'stepSize' => 1,
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
            'elements' => [
                'bar' => [
                    'borderWidth' => 2,
                ]
            ],
        ];
    }

    public function getHeading(): ?string
    {
        $filters = session('supplier_product_filters', []);
        return 'Supplier & Product Diversity (' . POReportSupplierProduct::getPeriodLabel($filters) . ')';
    }
}
