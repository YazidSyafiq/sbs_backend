<?php

namespace App\Filament\Resources\POReportProductResource\Widgets;

use App\Models\POReportProduct;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class POReportProductTopProducts extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('po_product_filters', []);
        $topProducts = POReportProduct::getTopProfitableProducts($filters, 6);

        $stats = [];

        foreach ($topProducts as $product) {
            $productName = strlen($product->product_name) > 15 ?
                          substr($product->product_name, 0, 15) . '...' :
                          $product->product_name;

            $stats[] = Stat::make($productName, 'Rp ' . number_format($product->total_profit, 0, ',', '.'))
                ->description('Margin: ' . $product->profit_margin . '% | Qty: ' . number_format($product->total_quantity))
                ->descriptionIcon($product->profit_margin >= 20 ? 'heroicon-m-trophy' :
                                ($product->profit_margin >= 10 ? 'heroicon-m-star' : 'heroicon-m-chart-bar'))
                ->color($product->profit_margin >= 20 ? 'success' :
                       ($product->profit_margin >= 10 ? 'warning' : 'info'));
        }

        if ($topProducts->isEmpty()) {
            $stats[] = Stat::make('No Data', 'No product data found')
                ->description('No products match the current filters')
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
        return 'Most Profitable Products (From Paid Orders)';
    }

    public function getDescription(): ?string
    {
        return 'Products ranked by total profit generated from paid orders only';
    }
}
