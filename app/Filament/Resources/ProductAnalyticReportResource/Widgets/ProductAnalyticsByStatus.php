<?php

namespace App\Filament\Resources\ProductAnalyticReportResource\Widgets;

use App\Models\ProductAnalyticReport;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProductAnalyticsByStatus extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('product_analytics_filters', []);
        $products = ProductAnalyticReport::getProductsByDetailedStatus($filters, 6);

        $stats = [];

        foreach ($products as $product) {
            $statusText = match($product->detailed_status) {
                'expired' => 'EXPIRED',
                'out_of_stock' => 'OUT OF STOCK',
                'expiring_soon' => 'EXPIRING SOON',
                'low_stock' => 'LOW STOCK',
                default => 'ATTENTION'
            };

            $description = 'Stock: ' . $product->stock . ' units';
            if ($product->need_purchase_qty > 0) {
                $description .= ' | Need PO: ' . $product->need_purchase_qty;
            }
            if ($product->category) {
                $description .= ' | ' . $product->category->name;
            }

            $stats[] = Stat::make($product->display_name, $statusText)
                ->description($description)
                ->descriptionIcon($this->getStatusIcon($product->detailed_status))
                ->color($this->getStatusColor($product->detailed_status));
        }

        if ($products->isEmpty()) {
            $stats[] = Stat::make('All Good!', 'No products need attention')
                ->description('All products are in good condition')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success');
        }

        return $stats;
    }

    private function getStatusIcon(string $status): string
    {
        return match($status) {
            'expired' => 'heroicon-m-x-circle',
            'out_of_stock' => 'heroicon-m-x-circle',
            'expiring_soon' => 'heroicon-m-clock',
            'low_stock' => 'heroicon-m-exclamation-triangle',
            default => 'heroicon-m-information-circle'
        };
    }

    private function getStatusColor(string $status): string
    {
        return match($status) {
            'expired' => 'danger',
            'out_of_stock' => 'danger',
            'expiring_soon' => 'warning',
            'low_stock' => 'warning',
            default => 'gray'
        };
    }

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        return 'Products Requiring Immediate Attention';
    }

    public function getDescription(): ?string
    {
        return 'Individual products with critical status issues ordered by priority';
    }
}
