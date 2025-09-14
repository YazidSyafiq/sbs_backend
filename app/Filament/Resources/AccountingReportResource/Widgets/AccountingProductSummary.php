<?php

namespace App\Filament\Resources\AccountingReportResource\Widgets;

use App\Models\AccountingReport;
use App\Models\ProductBatch;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountingProductSummary extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('accounting_filters', []);
        $productSales = AccountingReport::getProductSalesAnalysis($filters);

        if ($productSales->isEmpty()) {
            return [
                Stat::make('No Product Data', 'No product sales found')
                    ->description('No products sold in the selected period')
                    ->descriptionIcon('heroicon-m-cube')
                    ->color('gray'),
            ];
        }

        $totalProducts = $productSales->count();
        $totalOrders = $productSales->sum('total_orders');
        $totalQuantitySold = $productSales->sum('total_quantity_sold');
        $totalRevenue = $productSales->sum('total_revenue');
        $totalCost = $productSales->sum('total_cost');
        $totalProfit = $productSales->sum('total_profit');
        $avgProfitMargin = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0;

        // Find best and worst performing products
        $bestProduct = $productSales->sortByDesc('total_profit')->first();
        $worstProduct = $productSales->sortBy('total_profit')->first();

        // Get current inventory status
        $currentInventoryValue = ProductBatch::where('quantity', '>', 0)
            ->selectRaw('SUM(quantity * cost_price) as total_value')
            ->first()->total_value ?? 0;

        return [
            Stat::make('Product Sales Performance', $totalProducts . ' different products')
                ->description('Across ' . number_format($totalOrders) . ' orders • ' . number_format($totalQuantitySold) . ' units sold')
                ->descriptionIcon('heroicon-m-cube')
                ->color('blue'),

            Stat::make('Products Revenue & COGS', 'Rp ' . number_format($totalRevenue, 0, ',', '.'))
                ->description('COGS: Rp ' . number_format($totalCost, 0, ',', '.') . ' • Profit: Rp ' . number_format($totalProfit, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color($totalProfit > 0 ? 'success' : ($totalProfit < 0 ? 'danger' : 'gray')),

            Stat::make('Inventory & Profit Analysis', $avgProfitMargin . '% avg margin')
                ->description('Current Inventory: Rp ' . number_format($currentInventoryValue, 0, ',', '.') . ' • Best: ' . ($bestProduct ? $bestProduct->product_name . ' (' . $bestProduct->profit_margin . '%)' : 'N/A'))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($avgProfitMargin > 0 ? 'success' : ($avgProfitMargin < 0 ? 'danger' : 'gray')),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        return 'Products Performance Summary (FIFO-Based)';
    }

    public function getDescription(): ?string
    {
        return 'Product sales analysis based on FIFO cost calculation from ProductBatch system';
    }
}
