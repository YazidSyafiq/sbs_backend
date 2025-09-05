<?php

namespace App\Filament\Resources\POReportProductResource\Widgets;

use App\Models\POReportProduct;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class POReportProductByType extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('po_accounting_filters', []);

        // Use POReportProduct model method
        $stats = POReportProduct::getFilteredOverviewStats($filters);
        $statusStats = POReportProduct::getFilteredStatsByType($filters);

        $statsArray = [
            // Credit Purchase Stats with breakdown
            Stat::make('Credit POs', number_format($stats->credit_count) . ' orders')
                ->description('Total Value: Rp ' . number_format($stats->credit_total_amount, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('warning'),

            Stat::make('Credit Received', 'Rp ' . number_format($stats->credit_paid_amount, 0, ',', '.'))
                ->description($stats->credit_payment_rate . '% paid')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Credit Outstanding', 'Rp ' . number_format($stats->credit_outstanding, 0, ',', '.'))
                ->description((100 - $stats->credit_payment_rate) . '% unpaid')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($stats->credit_outstanding > 0 ? 'danger' : 'success'),

            // Cash Purchase Stats with breakdown
            Stat::make('Cash POs', number_format($stats->cash_count) . ' orders')
                ->description('Total Value: Rp ' . number_format($stats->cash_total_amount, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Cash Received', 'Rp ' . number_format($stats->cash_paid_amount, 0, ',', '.'))
                ->description($stats->cash_payment_rate . '% paid')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Cash Outstanding', 'Rp ' . number_format($stats->cash_outstanding, 0, ',', '.'))
                ->description((100 - $stats->cash_payment_rate) . '% unpaid')
                ->descriptionIcon('heroicon-m-clock')
                ->color($stats->cash_outstanding > 0 ? 'danger' : 'success'),
        ];

        // Add status-based stats dengan breakdown
        foreach ($statusStats as $status => $stat) {
            $statsArray[] = Stat::make($status . ' Orders', number_format($stat->count))
                ->description('Total: Rp ' . number_format($stat->total_amount, 0, ',', '.') . ' | Paid: ' . $stat->payment_rate . '%')
                ->descriptionIcon(match($status) {
                    'Requested' => 'heroicon-m-paper-airplane',
                    'Processing' => 'heroicon-m-arrows-pointing-in',
                    'Shipped' => 'heroicon-m-truck',
                    'Received' => 'heroicon-m-check-circle',
                    'Done' => 'heroicon-m-check-badge',
                    default => 'heroicon-m-document'
                })
                ->color(match($status) {
                    'Requested' => 'amber',
                    'Processing' => 'blue',
                    'Shipped' => 'purple',
                    'Received' => 'emerald',
                    'Done' => 'success',
                    default => 'gray'
                });
        }

        return $statsArray;
    }

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        return 'Purchase Analysis by Type & Status';
    }
}
