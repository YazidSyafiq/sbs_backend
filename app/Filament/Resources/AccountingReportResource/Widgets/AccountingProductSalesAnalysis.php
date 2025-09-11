<?php

namespace App\Filament\Resources\AccountingReportResource\Widgets;

use App\Models\AccountingReport;
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

        // Show all products (no limit) but use pagination-friendly approach
        foreach ($productSales as $product) {
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
                    'Cost: Rp ' . number_format($product->total_cost, 0, ',', '.') . ' • ' .
                    'Profit: ' . $profitText . ' (' . $product->profit_margin . '%)'
                )
                ->descriptionIcon('heroicon-m-cube')
                ->color($color)
                ->extraAttributes([
                    'class' => 'product-stat-card',
                    'style' => 'min-height: 120px;'
                ]);
        }

        return $stats;
    }

    protected function getColumns(): int
    {
        return 2; // 2 columns for better readability
    }

    public function getHeading(): ?string
    {
        return 'Individual Product Performance';
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

        return 'Detailed analysis of ' . $totalProducts . ' products • Total Revenue: Rp ' .
               number_format($totalRevenue, 0, ',', '.') . ' • Total Profit: Rp ' .
               number_format($totalProfit, 0, ',', '.');
    }
}
