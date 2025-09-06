<?php

namespace App\Filament\Resources\ProductAnalyticReportResource\Widgets;

use App\Models\ProductCategory;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProductAnalyticsFilterInfo extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('product_analytics_filters', []);

        // Check for truly active filters (excluding default purchase dates)
        $activeFilters = collect($filters)
            ->filter(function($value, $key) {
                // Exclude purchase date filters from active filter detection
                if (in_array($key, ['purchase_date_from', 'purchase_date_until'])) {
                    return false;
                }

                if (is_array($value)) {
                    return !empty($value);
                }
                return !is_null($value) && $value !== false && $value !== '';
            });

        if ($activeFilters->count() === 0) {
            return [
                Stat::make('Filter Status', 'No Filters Applied')
                    ->description('Use "Filter Products" button above to apply filters to all widgets')
                    ->descriptionIcon('heroicon-m-funnel')
                    ->color('gray'),
            ];
        }

        $stats = [];
        $filterCount = 0;

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
            $filterCount++;
        }

        // Stock Status filter
        if (!empty($filters['stock_status'])) {
            $statusLabels = array_map(function($status) {
                return match($status) {
                    'out_of_stock' => 'Out of Stock',
                    'low_stock' => 'Low Stock',
                    'in_stock' => 'In Stock',
                    default => ucfirst(str_replace('_', ' ', $status))
                };
            }, $filters['stock_status']);

            $statusText = count($statusLabels) > 2 ?
                count($statusLabels) . ' statuses' :
                implode(', ', $statusLabels);

            $stats[] = Stat::make('Stock Status', $statusText)
                ->description('Stock conditions')
                ->descriptionIcon('heroicon-m-cube')
                ->color('blue');
            $filterCount++;
        }

        // If we have exactly 1 more filter, show it. Otherwise summarize
        $remainingFilters = $activeFilters->count() - $filterCount;

        if ($remainingFilters === 1 && count($stats) < 2) {
            if (!empty($filters['expiry_status'])) {
                $expiryLabels = array_map(function($status) {
                    return match($status) {
                        'expired' => 'Expired',
                        'expiring_soon' => 'Expiring Soon',
                        'fresh' => 'Fresh',
                        'no_expiry' => 'No Expiry',
                        default => ucfirst(str_replace('_', ' ', $status))
                    };
                }, $filters['expiry_status']);

                $expiryText = count($expiryLabels) > 2 ?
                    count($expiryLabels) . ' expiry types' :
                    implode(', ', $expiryLabels);

                $stats[] = Stat::make('Expiry Status', $expiryText)
                    ->description('Expiry conditions')
                    ->descriptionIcon('heroicon-m-calendar-days')
                    ->color('blue');
            } elseif (!empty($filters['entry_date_from']) || !empty($filters['entry_date_until'])) {
                $dateFrom = !empty($filters['entry_date_from']) ? date('j M', strtotime($filters['entry_date_from'])) : null;
                $dateTo = !empty($filters['entry_date_until']) ? date('j M Y', strtotime($filters['entry_date_until'])) : null;

                if ($dateFrom && $dateTo) {
                    $dateRange = $dateFrom . ' – ' . $dateTo;
                } elseif ($dateFrom) {
                    $dateRange = 'From ' . $dateFrom;
                } else {
                    $dateRange = 'Until ' . $dateTo;
                }

                $stats[] = Stat::make('Entry Date', $dateRange)
                    ->description('Product entry period')
                    ->descriptionIcon('heroicon-m-calendar')
                    ->color('blue');
            } elseif (!empty($filters['price_min']) || !empty($filters['price_max'])) {
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
            } elseif (!empty($filters['need_purchase_only'])) {
                $stats[] = Stat::make('Special Filter', 'Need Purchase')
                    ->description('Products requiring purchase')
                    ->descriptionIcon('heroicon-m-shopping-cart')
                    ->color('orange');
            }
        } elseif ($remainingFilters > 1 || count($stats) >= 2) {
            // Summarize remaining filters
            $filterSummary = [];

            if (!empty($filters['expiry_status']) && $filterCount < 2) {
                $filterSummary[] = 'Expiry: ' . count($filters['expiry_status']) . ' types';
            }

            if (!empty($filters['entry_date_from']) || !empty($filters['entry_date_until'])) {
                $filterSummary[] = 'Entry date range';
            }

            if (!empty($filters['price_min']) || !empty($filters['price_max'])) {
                $filterSummary[] = 'Price range';
            }

            if (!empty($filters['need_purchase_only'])) {
                $filterSummary[] = 'Need purchase only';
            }

            if (!empty($filterSummary)) {
                $stats[] = Stat::make('More Filters', count($filterSummary) . ' additional')
                    ->description(implode(' | ', $filterSummary))
                    ->descriptionIcon('heroicon-m-adjustments-horizontal')
                    ->color('blue');
            }
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
