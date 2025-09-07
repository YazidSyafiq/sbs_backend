<?php

namespace App\Filament\Resources\ServiceAnalyticReportResource\Widgets;

use App\Models\ProductCategory;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ServiceAnalyticsFilterInfo extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('service_analytics_filters', []);

        $activeFilters = collect($filters)
            ->filter(function($value, $key) {
                if (is_array($value)) {
                    return !empty($value);
                }
                return !is_null($value) && $value !== false && $value !== '';
            });

        if ($activeFilters->count() === 0) {
            return [
                Stat::make('Filter Status', 'No Filters Applied')
                    ->description('Use "Filter Services" button above to apply filters')
                    ->descriptionIcon('heroicon-m-funnel')
                    ->color('gray'),
            ];
        }

        $stats = [];

        // Category filter
        if (!empty($filters['category_id'])) {
            $categoryNames = ProductCategory::whereIn('id', $filters['category_id'])->pluck('name')->toArray();
            $categoryText = count($categoryNames) > 2 ?
                count($categoryNames) . ' categories' :
                implode(', ', $categoryNames);

            $stats[] = Stat::make('Categories', $categoryText)
                ->description('Selected categories')
                ->descriptionIcon('heroicon-m-tag')
                ->color('blue');
        }

        // Price range filter
        if (!empty($filters['price_min']) || !empty($filters['price_max'])) {
            $priceRange = '';
            if (!empty($filters['price_min'])) {
                $priceRange .= 'Rp ' . number_format($filters['price_min']);
            }
            if (!empty($filters['price_max'])) {
                if ($priceRange) $priceRange .= ' – ';
                $priceRange .= 'Rp ' . number_format($filters['price_max']);
            }

            $stats[] = Stat::make('Price Range', $priceRange)
                ->description('Price filter')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('blue');
        }

        return $stats;
    }

    protected function getColumns(): int
    {
        return 2;
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
