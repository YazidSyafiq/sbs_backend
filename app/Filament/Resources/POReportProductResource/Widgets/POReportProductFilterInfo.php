<?php

namespace App\Filament\Resources\POReportProductResource\Widgets;

use App\Models\Branch;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class POReportProductFilterInfo extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('po_product_filters', []);
        $activeFilters = collect($filters)
            ->filter(function($value) {
                if (is_array($value)) {
                    return !empty($value);
                }
                return !is_null($value) && $value !== false && $value !== '';
            });

        if ($activeFilters->count() === 0) {
            return [
                Stat::make('Filter Status', 'No Filters Applied')
                    ->description('Use "Filter Data" button above to apply filters to all widgets')
                    ->descriptionIcon('heroicon-m-funnel')
                    ->color('gray'),
            ];
        }

        $stats = [];
        $filterCount = 0;

        // Branch filter
        if (!empty($filters['branch_id'])) {
            $branchName = Branch::find($filters['branch_id'])->name ?? 'Unknown Branch';
            $stats[] = Stat::make('Branch', $branchName)
                ->description('Selected branch')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('blue');
            $filterCount++;
        }

        // If we have exactly 1 more filter, show it. Otherwise summarize
        $remainingFilters = $activeFilters->count() - $filterCount;

        if ($remainingFilters === 1) {
            // Show the one remaining filter
            if (!empty($filters['type_po'])) {
                $types = implode(', ', array_map('ucfirst', $filters['type_po']));
                $stats[] = Stat::make('Type', $types)
                    ->description('Purchase types')
                    ->descriptionIcon('heroicon-m-shopping-cart')
                    ->color('blue');
            } elseif (!empty($filters['status'])) {
                $statuses = implode(', ', $filters['status']);
                $stats[] = Stat::make('Status', $statuses)
                    ->description('Order statuses')
                    ->descriptionIcon('heroicon-m-clipboard-document-check')
                    ->color('blue');
            } elseif (!empty($filters['status_paid'])) {
                $payments = implode(', ', array_map('ucfirst', $filters['status_paid']));
                $stats[] = Stat::make('Payment', $payments)
                    ->description('Payment statuses')
                    ->descriptionIcon('heroicon-m-banknotes')
                    ->color('blue');
            } elseif (!empty($filters['date_from']) || !empty($filters['date_until'])) {
                $dateFrom = !empty($filters['date_from']) ? date('j M', strtotime($filters['date_from'])) : null;
                $dateTo = !empty($filters['date_until']) ? date('j M Y', strtotime($filters['date_until'])) : null;

                if ($dateFrom && $dateTo) {
                    $dateRange = $dateFrom . ' – ' . $dateTo;
                } elseif ($dateFrom) {
                    $dateRange = 'From ' . $dateFrom;
                } else {
                    $dateRange = 'Until ' . $dateTo;
                }

                $stats[] = Stat::make('Date Range', $dateRange)
                    ->description('Custom period')
                    ->descriptionIcon('heroicon-m-calendar-days')
                    ->color('blue');
            } elseif (!empty($filters['outstanding_only'])) {
                $stats[] = Stat::make('Special Filter', 'Outstanding Only')
                    ->description('Unpaid orders only')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('orange');
            }
        } elseif ($remainingFilters > 1) {
            // Summarize multiple remaining filters
            $filterSummary = [];

            if (!empty($filters['type_po'])) {
                $filterSummary[] = 'Type: ' . implode(', ', $filters['type_po']);
            }

            if (!empty($filters['status'])) {
                $filterSummary[] = 'Status: ' . implode(', ', $filters['status']);
            }

            if (!empty($filters['status_paid'])) {
                $filterSummary[] = 'Payment: ' . implode(', ', $filters['status_paid']);
            }

            if (!empty($filters['date_from']) || !empty($filters['date_until'])) {
                $dateFrom = !empty($filters['date_from']) ? date('j M', strtotime($filters['date_from'])) : null;
                $dateTo = !empty($filters['date_until']) ? date('j M Y', strtotime($filters['date_until'])) : null;

                if ($dateFrom && $dateTo) {
                    $filterSummary[] = 'Period: ' . $dateFrom . ' – ' . $dateTo;
                } elseif ($dateFrom) {
                    $filterSummary[] = 'From: ' . $dateFrom;
                } else {
                    $filterSummary[] = 'Until: ' . $dateTo;
                }
            }

            if (!empty($filters['outstanding_only'])) {
                $filterSummary[] = 'Outstanding only';
            }

            $stats[] = Stat::make('Additional Filters', $remainingFilters . ' more filters')
                ->description(implode(' | ', $filterSummary))
                ->descriptionIcon('heroicon-m-adjustments-horizontal')
                ->color('blue');
        }

        return $stats;
    }

    protected function getColumns(): int
    {
        return 2; // Always 2 columns for consistent layout
    }

    public function getHeading(): ?string
    {
        return 'Applied Filters';
    }

    public static function getSort(): int
    {
        return -1;
    }
}
