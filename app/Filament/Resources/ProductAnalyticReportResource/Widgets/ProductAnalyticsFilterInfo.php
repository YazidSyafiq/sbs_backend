<?php

namespace App\Filament\Resources\ProductAnalyticReportResource\Widgets;

use App\Models\ProductCategory;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProductAnalyticsFilterInfo extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('product_analytics_filters', []);

        $activeFilters = collect($filters)
            ->filter(function($value, $key) {
                if (is_array($value)) {
                    return !empty($value);
                }
                return !is_null($value) && $value !== '';
            });

        if ($activeFilters->count() === 0) {
            return [
                Stat::make('Filter Status', 'No Filters Applied')
                    ->description('Use "Filter Analytics" button to apply filters')
                    ->descriptionIcon('heroicon-m-funnel')
                    ->color('gray'),
            ];
        }

        $stats = [];

        // Product filter
        if (!empty($filters['product_ids'])) {
            $productCount = count($filters['product_ids']);
            if ($productCount <= 3) {
                $productNames = Product::whereIn('id', $filters['product_ids'])
                    ->pluck('name')
                    ->map(fn($name) => strlen($name) > 15 ? substr($name, 0, 15) . '...' : $name)
                    ->toArray();
                $productText = implode(', ', $productNames);
            } else {
                $productText = $productCount . ' products selected';
            }

            $stats[] = Stat::make('Products', $productText)
                ->description('Selected products')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('blue');
        }

        // Category filter
        if (!empty($filters['category_ids'])) {
            $categoryNames = ProductCategory::whereIn('id', $filters['category_ids'])->pluck('name')->toArray();
            $categoryText = count($categoryNames) > 2 ?
                count($categoryNames) . ' categories' :
                implode(', ', $categoryNames);

            $stats[] = Stat::make('Categories', $categoryText)
                ->description('Selected categories')
                ->descriptionIcon('heroicon-m-tag')
                ->color('blue');
        }

        // Date range filter
        if (!empty($filters['date_from']) || !empty($filters['date_until'])) {
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
                ->description('Stock entry period')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('blue');
        }

        return $stats;
    }

    protected function getColumns(): int
    {
        return 3;
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
