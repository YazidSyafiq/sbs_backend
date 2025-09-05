<?php

namespace App\Filament\Resources\POReportSupplierProductResource\Widgets;

use App\Models\POReportSupplierProduct;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\ProductCategory;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class POReportSupplierProductOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('supplier_product_filters', []);

        // Use POReportSupplierProduct model method
        $stats = POReportSupplierProduct::getFilteredOverviewStats($filters);

        // Get contextual stats for the 6th card
        $contextStats = $this->getContextualStats($filters, $stats);

        return [
            Stat::make('Total Purchase Orders', number_format($stats->total_count))
                ->description('All active POs')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('info'),

            Stat::make('Total PO Value', 'Rp ' . number_format($stats->total_po_amount, 0, ',', '.'))
                ->description('Combined order value')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary'),

            Stat::make('Total Products Ordered', number_format($stats->total_quantity))
                ->description('Units across all products')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info'),

            Stat::make('Amount Paid to Suppliers', 'Rp ' . number_format($stats->paid_amount, 0, ',', '.'))
                ->description($stats->payment_rate . '% of total PO value')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Outstanding Payments', 'Rp ' . number_format($stats->outstanding_debt, 0, ',', '.'))
                ->description((100 - $stats->payment_rate) . '% of total PO value')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($stats->outstanding_debt > 0 ? 'danger' : 'success'),

            // Context-aware 6th stat
            Stat::make($contextStats['title'], $contextStats['value'])
                ->description($contextStats['description'])
                ->descriptionIcon($contextStats['icon'])
                ->color($contextStats['color']),
        ];
    }

    /**
     * Get contextual statistics based on active filters
     */
    private function getContextualStats($filters, $currentStats): array
    {
        // Check what filters are active
        $hasSupplierFilter = !empty($filters['supplier_id']);
        $hasProductFilter = !empty($filters['product_id']);
        $hasCategoryFilter = !empty($filters['category_id']);
        $hasDateFilter = !empty($filters['date_from']) || !empty($filters['date_until']);
        $hasTypeFilter = !empty($filters['type_po']);
        $hasOutstandingFilter = !empty($filters['outstanding_only']);

        if ($hasSupplierFilter) {
            return $this->getSupplierFilterContext($filters, $currentStats);
        } elseif ($hasProductFilter) {
            return $this->getProductFilterContext($filters, $currentStats);
        } elseif ($hasCategoryFilter) {
            return $this->getCategoryFilterContext($filters, $currentStats);
        } elseif ($hasDateFilter) {
            return $this->getDateFilterContext($filters, $currentStats);
        } elseif ($hasTypeFilter) {
            return $this->getTypeFilterContext($filters, $currentStats);
        } elseif ($hasOutstandingFilter) {
            return $this->getOutstandingFilterContext($currentStats);
        } else {
            return $this->getDefaultContext();
        }
    }

    /**
     * Context for supplier filter
     */
    private function getSupplierFilterContext($filters, $currentStats): array
    {
        $supplier = Supplier::find($filters['supplier_id']);
        $supplierName = $supplier ? $supplier->name : 'Unknown Supplier';

        return [
            'title' => 'Supplier Focus',
            'value' => $supplierName,
            'description' => 'Products: ' . number_format($currentStats->unique_products) . ' | Avg Order: Rp ' . number_format($currentStats->average_order_value, 0, ',', '.'),
            'icon' => 'heroicon-m-building-storefront',
            'color' => $currentStats->payment_rate >= 80 ? 'success' : 'info'
        ];
    }

    /**
     * Context for product filter
     */
    private function getProductFilterContext($filters, $currentStats): array
    {
        $product = Product::find($filters['product_id']);
        $productName = $product ? $product->name : 'Unknown Product';

        return [
            'title' => 'Product Focus',
            'value' => $productName,
            'description' => 'Suppliers: ' . number_format($currentStats->unique_suppliers) . ' | Total Qty: ' . number_format($currentStats->total_quantity),
            'icon' => 'heroicon-m-cube',
            'color' => $currentStats->payment_rate >= 80 ? 'success' : 'info'
        ];
    }

    /**
     * Context for category filter
     */
    private function getCategoryFilterContext($filters, $currentStats): array
    {
        $categoryName = ProductCategory::find($filters['category_id'])->name ?? 'Unknown Category';

        return [
            'title' => 'Category Focus',
            'value' => $categoryName,
            'description' => 'Products: ' . number_format($currentStats->unique_products) . ' | Suppliers: ' . number_format($currentStats->unique_suppliers),
            'icon' => 'heroicon-m-tag',
            'color' => $currentStats->payment_rate >= 80 ? 'success' : 'purple'
        ];
    }

    /**
     * Context for date filter
     */
    private function getDateFilterContext($filters, $currentStats): array
    {
        $dateFrom = !empty($filters['date_from']) ? $filters['date_from'] : null;
        $dateTo = !empty($filters['date_until']) ? $filters['date_until'] : null;

        if ($dateFrom && $dateTo) {
            $daysDiff = \Carbon\Carbon::parse($dateFrom)->diffInDays(\Carbon\Carbon::parse($dateTo));

            if ($daysDiff <= 7) {
                $periodType = 'Week';
                $icon = 'heroicon-m-calendar';
            } elseif ($daysDiff <= 31) {
                $periodType = 'Month';
                $icon = 'heroicon-m-calendar';
            } elseif ($daysDiff <= 92) {
                $periodType = 'Quarter';
                $icon = 'heroicon-m-chart-bar';
            } else {
                $periodType = 'Period';
                $icon = 'heroicon-m-calendar-days';
            }

            $title = "Selected {$periodType}";
        } elseif ($dateFrom) {
            $title = 'From ' . \Carbon\Carbon::parse($dateFrom)->format('M Y');
            $icon = 'heroicon-m-arrow-right';
        } else {
            $title = 'Until ' . \Carbon\Carbon::parse($dateTo)->format('M Y');
            $icon = 'heroicon-m-arrow-left';
        }

        return [
            'title' => $title,
            'value' => 'Avg Order: Rp ' . number_format($currentStats->average_order_value, 0, ',', '.'),
            'description' => 'Suppliers: ' . number_format($currentStats->unique_suppliers) . ' | Products: ' . number_format($currentStats->unique_products),
            'icon' => $icon ?? 'heroicon-m-calendar-days',
            'color' => $currentStats->payment_rate >= 80 ? 'success' : 'info'
        ];
    }

    /**
     * Context for type filter
     */
    private function getTypeFilterContext($filters, $currentStats): array
    {
        $types = array_map('ucfirst', $filters['type_po']);
        $typeText = implode(' & ', $types);

        return [
            'title' => $typeText . ' Orders',
            'value' => number_format($currentStats->total_count) . ' orders',
            'description' => 'Payment Rate: ' . $currentStats->payment_rate . '% | Avg: Rp ' . number_format($currentStats->average_order_value, 0, ',', '.'),
            'icon' => count($types) === 1 && $types[0] === 'Credit' ? 'heroicon-m-credit-card' : 'heroicon-m-shopping-cart',
            'color' => count($types) === 1 && $types[0] === 'Credit' ? 'warning' : 'success'
        ];
    }

    /**
     * Context for outstanding filter
     */
    private function getOutstandingFilterContext($currentStats): array
    {
        return [
            'title' => 'Outstanding Analysis',
            'value' => 'Rp ' . number_format($currentStats->outstanding_debt, 0, ',', '.'),
            'description' => 'Suppliers: ' . number_format($currentStats->unique_suppliers) . ' | Products: ' . number_format($currentStats->unique_products),
            'icon' => 'heroicon-m-exclamation-triangle',
            'color' => 'danger'
        ];
    }

    /**
     * Default context when no filters are applied
     */
    private function getDefaultContext(): array
    {
        // Show current month when no filters are applied
        $thisMonthFilters = [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_until' => now()->endOfMonth()->toDateString(),
        ];
        $thisMonthStats = POReportSupplierProduct::getFilteredOverviewStats($thisMonthFilters);

        return [
            'title' => 'This Month',
            'value' => number_format($thisMonthStats->total_count) . ' orders',
            'description' => 'Value: Rp ' . number_format($thisMonthStats->total_po_amount, 0, ',', '.') . ' | Payment: ' . $thisMonthStats->payment_rate . '%',
            'icon' => 'heroicon-m-calendar',
            'color' => $thisMonthStats->payment_rate >= 80 ? 'success' : 'info'
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        return 'Supplier-Product Overview';
    }
}
