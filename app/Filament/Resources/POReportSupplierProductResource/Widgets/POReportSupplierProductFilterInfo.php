<?php

namespace App\Filament\Resources\POReportSupplierProductResource\Widgets;

use App\Models\Supplier;
use App\Models\Product;
use App\Models\ProductCategory;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class POReportSupplierProductFilterInfo extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('supplier_product_filters', []);
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

        // Supplier filter
        if (!empty($filters['supplier_id'])) {
            $supplier = Supplier::find($filters['supplier_id']);
            $supplierName = $supplier ? $supplier->name . ' (' . $supplier->code . ')' : 'Unknown Supplier';
            $stats[] = Stat::make('Supplier', $supplierName)
                ->description('Selected supplier')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('blue');
            $filterCount++;
        }

        // Product filter
        if (!empty($filters['product_id'])) {
            $product = Product::find($filters['product_id']);
            $productName = $product ? $product->name . ' (' . $product->code . ')' : 'Unknown Product';
            $stats[] = Stat::make('Product', $productName)
                ->description('Selected product')
                ->descriptionIcon('heroicon-m-cube')
                ->color('green');
            $filterCount++;
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $categoryName = ProductCategory::find($filters['category_id'])->name ?? 'Unknown Category';
            $stats[] = Stat::make('Category', $categoryName)
                ->description('Product category')
                ->descriptionIcon('heroicon-m-tag')
                ->color('purple');
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
                ->description(implode(' | ', array_slice($filterSummary, 0, 2))) // Limit to first 2 items
                ->descriptionIcon('heroicon-m-adjustments-horizontal')
                ->color('blue');
        }

        // Fill remaining slots if less than 3 stats
        while (count($stats) < 3) {
            $stats[] = Stat::make('', '')
                ->description('')
                ->color('gray')
                ->extraAttributes(['class' => 'invisible']);
        }

        return $stats;
    }

    protected function getColumns(): int
    {
        return 3; // Always 3 columns for consistent layout
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
