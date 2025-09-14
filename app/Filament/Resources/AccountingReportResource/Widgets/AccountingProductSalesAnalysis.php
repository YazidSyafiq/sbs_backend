<?php

namespace App\Filament\Resources\AccountingReportResource\Widgets;

use App\Models\AccountingReport;
use App\Models\ProductBatch;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountingProductSalesAnalysis extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $filters = session('accounting_filters', []);
        $productSales = AccountingReport::getProductSalesAnalysis($filters);

        $stats = [];

        if ($productSales->isEmpty()) {
            return [
                Stat::make('No Product Sales', 'No products were sold')
                    ->description('No product sales found in the selected period')
                    ->descriptionIcon('heroicon-m-cube')
                    ->color('gray'),
            ];
        }

        // Show all products with current batch info
        foreach ($productSales as $product) {
            // Get current inventory for this product
            $currentBatches = ProductBatch::where('product_id', $product->product_id)
                ->where('quantity', '>', 0)
                ->count();

            $currentStock = ProductBatch::where('product_id', $product->product_id)
                ->where('quantity', '>', 0)
                ->sum('quantity');

            $currentInventoryValue = ProductBatch::where('product_id', $product->product_id)
                ->where('quantity', '>', 0)
                ->selectRaw('SUM(quantity * cost_price) as total_value')
                ->first()->total_value ?? 0;

            // Format profit dengan warna dan tanda
            $profitText = $product->total_profit >= 0 ? '+' : '';
            $profitText .= 'Rp ' . number_format($product->total_profit, 0, ',', '.');

            // Determine color based on profit
            $color = 'gray';
            if ($product->total_profit > 0) {
                $color = 'success';
            } elseif ($product->total_profit < 0) {
                $color = 'danger';
            }

            $stats[] = Stat::make(
                $product->product_name . ' (' . $product->product_code . ')',
                'Ordered ' . number_format($product->total_orders) . ' times • ' .
                number_format($product->total_quantity_sold) . ' units sold'
            )
                ->description(
                    'Revenue: Rp ' . number_format($product->total_revenue, 0, ',', '.') . ' • ' .
                    'COGS: Rp ' . number_format($product->total_cost, 0, ',', '.') . ' • ' .
                    'Profit: ' . $profitText . ' (' . $product->profit_margin . '%)' . ' • ' .
                    'Current: ' . number_format($currentStock) . ' units in ' . $currentBatches . ' batches • ' .
                    'Value: Rp ' . number_format($currentInventoryValue, 0, ',', '.')
                )
                ->descriptionIcon('heroicon-m-cube')
                ->color($color)
                ->extraAttributes([
                    'class' => 'product-stat-card',
                    'style' => 'min-height: 140px;'
                ]);
        }

        return $stats;
    }

    protected function getColumns(): int
    {
        return 2;
    }

    public function getHeading(): ?string
    {
        return 'Individual Product Performance (FIFO-Based)';
    }

    public function getDescription(): ?string
    {
        $filters = session('accounting_filters', []);
        $productSales = AccountingReport::getProductSalesAnalysis($filters);

        if ($productSales->isEmpty()) {
            return 'No product data available for the selected period';
        }

        $totalProducts = $productSales->count();
        $totalRevenue = $productSales->sum('total_revenue');
        $totalProfit = $productSales->sum('total_profit');

        // Get current inventory summary
        $currentInventoryValue = ProductBatch::where('quantity', '>', 0)
            ->selectRaw('SUM(quantity * cost_price) as total_value')
            ->first()->total_value ?? 0;

        return 'Analysis of ' . $totalProducts . ' products with FIFO costing • Total Revenue: Rp ' .
               number_format($totalRevenue, 0, ',', '.') . ' • Total Profit: Rp ' .
               number_format($totalProfit, 0, ',', '.') . ' • Current Inventory: Rp ' .
               number_format($currentInventoryValue, 0, ',', '.');
    }
}
