<?php

namespace App\Filament\Resources\POReportServiceResource\Widgets;

use App\Models\POReportService;
use Filament\Widgets\ChartWidget;

class POReportServiceTechnicianChart extends ChartWidget
{
    protected static ?string $heading = 'Technician Debt vs Profit Analysis';
    protected static ?string $maxHeight = '500px';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $filters = session('po_service_filters', []);
        $technicianAnalysis = POReportService::getFilteredTechnicianAnalysis($filters);

        // Take top 12 technicians by total services for better visibility
        $topTechnicians = $technicianAnalysis->sortByDesc('total_services')->take(12);

        $labels = $topTechnicians->pluck('technician_name')->toArray();

        // Convert to thousands for better readability
        $actualDebts = $topTechnicians->pluck('actual_debt')->map(fn($amount) => round($amount / 1000, 0))->toArray();
        $realizedProfits = $topTechnicians->pluck('realized_profit')->map(fn($amount) => round($amount / 1000, 0))->toArray();
        $potentialProfits = $topTechnicians->pluck('potential_profit')->map(fn($amount) => round($amount / 1000, 0))->toArray();
        $totalCosts = $topTechnicians->pluck('total_cost_owed')->map(fn($amount) => round($amount / 1000, 0))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Outstanding Debt',
                    'data' => $actualDebts,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'borderWidth' => 2,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                ],
                [
                    'label' => 'Realized Profit',
                    'data' => $realizedProfits,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 2,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                ],
                [
                    'label' => 'Potential Profit',
                    'data' => $potentialProfits,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.8)',
                    'borderColor' => 'rgb(245, 158, 11)',
                    'borderWidth' => 2,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                ],
                [
                    'label' => 'Total Cost Owed',
                    'data' => $totalCosts,
                    'backgroundColor' => 'rgba(156, 163, 175, 0.8)',
                    'borderColor' => 'rgb(156, 163, 175)',
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
                            'size' => 12,
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
                        'text' => 'Amount (Thousand Rp)',
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
                        'text' => 'Technicians',
                        'font' => [
                            'size' => 12,
                            'weight' => 'bold'
                        ]
                    ],
                    'grid' => [
                        'display' => false,
                    ],
                    'ticks' => [
                        'maxRotation' => 45,
                        'minRotation' => 0,
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
        return 'Technician Financial Analysis (' . POReportService::getPeriodLabel($filters) . ')';
    }

    public function getDescription(): ?string
    {
        return 'Comparison of outstanding debt, realized profit, potential profit, and total cost owed for top technicians';
    }
}
