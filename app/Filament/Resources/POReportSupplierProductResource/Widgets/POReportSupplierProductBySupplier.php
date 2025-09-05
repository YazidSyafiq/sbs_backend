<?php

namespace App\Filament\Resources\POReportSupplierProductResource\Widgets;

use App\Models\POReportSupplierProduct;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class POReportSupplierProductBySupplier extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('supplier_product_filters', []);
        $stats = [];

        // Use POReportSupplierProduct model method
        $supplierStats = POReportSupplierProduct::getFilteredSupplierStats($filters);

        // Limit to first 10 suppliers to avoid overcrowding
        $supplierStats = $supplierStats->take(10);

        foreach ($supplierStats as $supplier) {
            $supplierName = $supplier->supplier_name ?: 'No Supplier';

            // Truncate supplier name if too long for better display
            $displayName = strlen($supplierName) > 20 ? substr($supplierName, 0, 20) . '...' : $supplierName;

            // Widget untuk total orders dan value
            $stats[] = Stat::make($displayName . ' - Orders', number_format($supplier->total_pos) . ' POs')
                ->description('Value: Rp ' . number_format($supplier->total_po_amount, 0, ',', '.') .
                           ' | Products: ' . number_format($supplier->unique_products))
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('info');

            // Widget untuk payment status dan details
            $paymentIcon = $supplier->payment_rate >= 80 ? 'heroicon-m-check-circle' :
                          ($supplier->payment_rate >= 50 ? 'heroicon-m-clock' : 'heroicon-m-exclamation-triangle');

            $stats[] = Stat::make($displayName . ' - Payment', $supplier->payment_rate . '% paid')
                ->description('Paid: Rp ' . number_format($supplier->paid_amount, 0, ',', '.') .
                           ' | Outstanding: Rp ' . number_format($supplier->outstanding_debt, 0, ',', '.'))
                ->descriptionIcon($paymentIcon)
                ->color($supplier->payment_rate >= 80 ? 'success' :
                       ($supplier->payment_rate >= 50 ? 'warning' : 'danger'));

            // Widget untuk quantity dan average
            $stats[] = Stat::make($displayName . ' - Details', number_format($supplier->total_quantity) . ' units')
                ->description('Avg PO: Rp ' . number_format($supplier->average_po_amount, 0, ',', '.') .
                           ' | Credit: ' . $supplier->credit_pos . ', Cash: ' . $supplier->cash_pos)
                ->descriptionIcon('heroicon-m-cube')
                ->color('purple');
        }

        if ($supplierStats->isEmpty()) {
            $stats[] = Stat::make('No Data', 'No supplier data found')
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
        $supplierStats = POReportSupplierProduct::getFilteredSupplierStats($filters);

        $totalSuppliers = $supplierStats->count();
        $showing = min($totalSuppliers, 10);

        $title = 'Analysis by Supplier';
        if ($totalSuppliers > 10) {
            $title .= " (Top {$showing} of {$totalSuppliers})";
        } elseif ($totalSuppliers > 0) {
            $title .= " ({$totalSuppliers} suppliers)";
        }

        return $title;
    }
}
