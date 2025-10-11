<?php

namespace App\Filament\Resources\POReportServiceResource\Widgets;

use App\Models\POReportService;
use Filament\Widgets\ChartWidget;

class POReportServiceBarChart extends ChartWidget
{
    protected static ?string $heading = 'Service Orders Count Trends';
    protected static ?string $maxHeight = '400px';

    // ADD THIS: Full width column span
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $filters = session('po_service_filters', []);

        $monthlyData = POReportService::getFilteredMonthlyTrends($filters);

        $labels = $monthlyData->pluck('period_name')->toArray();
        $poCounts = $monthlyData->pluck('total_pos')->toArray();
        $creditCounts = $monthlyData->pluck('credit_pos')->toArray();
        $cashCounts = $monthlyData->pluck('cash_pos')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Total Service Orders',
                    'data' => $poCounts,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 2,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                ],
                [
                    'label' => 'Credit Services',
                    'data' => $creditCounts,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.8)',
                    'borderColor' => 'rgb(245, 158, 11)',
                    'borderWidth' => 2,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                ],
                [
                    'label' => 'Cash Services',
                    'data' => $cashCounts,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
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
                        'text' => 'Number of Service Orders',
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
        $filters = session('po_service_filters', []);
        return 'Service Orders Count Trends (' . POReportService::getPeriodLabel($filters) . ')';
    }

    public function getDescription(): ?string
    {
        return 'Number of service purchase orders by type over time';
    }
}
