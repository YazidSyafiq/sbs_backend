<?php

namespace App\Filament\Resources\ServiceAnalyticReportResource\Widgets;

use App\Models\ServiceAnalyticReport;
use Filament\Widgets\ChartWidget;

class ServiceAnalyticsDistributionChart extends ChartWidget
{
    protected static ?string $heading = 'Service PO Status Breakdown';
    protected static ?string $maxHeight = '600px';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $filters = session('service_analytics_filters', []);
        $services = ServiceAnalyticReport::getServicePOStatusDistribution($filters, 8);

        if ($services->isEmpty()) {
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

        $labels = $services->pluck('display_name')->toArray();

        // Data for each status
        $requestedData = $services->pluck('requested_qty')->toArray();
        $approvedData = $services->pluck('approved_qty')->toArray();
        $progressData = $services->pluck('progress_qty')->toArray();
        $doneData = $services->pluck('done_qty')->toArray();
        $cancelledData = $services->pluck('cancelled_qty')->toArray();

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
                    'label' => 'Approved',
                    'data' => $approvedData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 1,
                    'borderRadius' => 0,
                ],
                [
                    'label' => 'In Progress',
                    'data' => $progressData,
                    'backgroundColor' => 'rgba(168, 85, 247, 0.8)',
                    'borderColor' => 'rgb(168, 85, 247)',
                    'borderWidth' => 1,
                    'borderRadius' => 0,
                ],
                [
                    'label' => 'Done',
                    'data' => $doneData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 1,
                    'borderRadius' => 0,
                ],
                [
                    'label' => 'Cancelled',
                    'data' => $cancelledData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                    'borderColor' => 'rgb(239, 68, 68)',
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
                        'text' => 'Number of Items',
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
                        'text' => 'Services',
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
