<?php

namespace App\Filament\Resources\POReportSupplierProductResource\Widgets;

use App\Models\POReportSupplierProduct;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class POReportSupplierProductByProduct extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('supplier_product_filters', []);
        $stats = [];

        // Use POReportSupplierProduct model method
        $productStats = POReportSupplierProduct::getFilteredProductStats($filters);

        // Limit to first 12 products to avoid overcrowding
        $productStats = $productStats->take(12);

        foreach ($productStats as $product) {
            $productName = $product->product_name ?: 'Unknown Product';
            $categoryName = $product->category_name ?: 'No Category';

            // Truncate product name if too long for better display
            $displayName = strlen($productName) > 18 ? substr($productName, 0, 18) . '...' : $productName;

            // Widget untuk total quantity dan orders
            $stats[] = Stat::make($displayName, number_format($product->total_quantity) . ' units')
                ->description('Orders: ' . number_format($product->total_pos) .
                           ' | Suppliers: ' . number_format($product->unique_suppliers))
                ->descriptionIcon('heroicon-m-cube')
                ->color('info');

            // Widget untuk total value dan payment status
            $paymentIcon = $product->payment_rate >= 80 ? 'heroicon-m-check-circle' :
                          ($product->payment_rate >= 50 ? 'heroicon-m-clock' : 'heroicon-m-exclamation-triangle');

            $stats[] = Stat::make($displayName . ' - Value', 'Rp ' . number_format($product->total_po_amount, 0, ',', '.'))
                ->description('Payment: ' . $product->payment_rate . '% | Outstanding: Rp ' .
                           number_format($product->outstanding_debt, 0, ',', '.'))
                ->descriptionIcon($paymentIcon)
                ->color($product->payment_rate >= 80 ? 'success' :
                       ($product->payment_rate >= 50 ? 'warning' : 'danger'));

            // Widget untuk details dan category
            $stats[] = Stat::make($displayName . ' - Details', $categoryName)
                ->description('Avg Price: Rp ' . number_format($product->average_unit_price, 0, ',', '.') .
                           ' | Credit: ' . $product->credit_pos . ', Cash: ' . $product->cash_pos)
                ->descriptionIcon('heroicon-m-tag')
                ->color('purple');
        }

        if ($productStats->isEmpty()) {
            $stats[] = Stat::make('No Data', 'No product data found')
                ->description('No purchase orders match the current filters')
                ->descriptionIcon('heroicon-m-information-circle')
                ->color('gray');
        }

        return $stats;
    }

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        $filters = session('supplier_product_filters', []);
        $productStats = POReportSupplierProduct::getFilteredProductStats($filters);

        $totalProducts = $productStats->count();
        $showing = min($totalProducts, 12);

        $title = 'Analysis by Product';
        if ($totalProducts > 12) {
            $title .= " (Top {$showing} of {$totalProducts})";
        } elseif ($totalProducts > 0) {
            $title .= " ({$totalProducts} products)";
        }

        return $title;
    }
}
