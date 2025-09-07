<?php

namespace App\Filament\Resources\POReportServiceResource\Widgets;

use App\Models\POReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Auth;

class POReportServiceByType extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('po_service_filters', []);
        $user = Auth::user();
        $isUserRole = $user && $user->hasRole('User');

        // Use POReportService model method
        $stats = POReportService::getFilteredOverviewStats($filters);
        $statusStats = POReportService::getFilteredStatsByType($filters);

        // Different labels based on user role
        $creditReceivedLabel = $isUserRole ? 'Credit Paid' : 'Credit Received';
        $creditOutstandingLabel = $isUserRole ? 'Credit Unpaid' : 'Credit Outstanding';
        $cashReceivedLabel = $isUserRole ? 'Cash Paid' : 'Cash Received';
        $cashOutstandingLabel = $isUserRole ? 'Cash Unpaid' : 'Cash Outstanding';

        $statsArray = [
            // Credit Purchase Stats with breakdown
            Stat::make('Credit Services', number_format($stats->credit_count) . ' orders')
                ->description('Total Value: Rp ' . number_format($stats->credit_total_amount, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('warning'),

            Stat::make($creditReceivedLabel, 'Rp ' . number_format($stats->credit_paid_amount, 0, ',', '.'))
                ->description($stats->credit_payment_rate . '% paid')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make($creditOutstandingLabel, 'Rp ' . number_format($stats->credit_outstanding, 0, ',', '.'))
                ->description((100 - $stats->credit_payment_rate) . '% unpaid')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($stats->credit_outstanding > 0 ? 'danger' : 'success'),

            // Cash Purchase Stats with breakdown
            Stat::make('Cash Services', number_format($stats->cash_count) . ' orders')
                ->description('Total Value: Rp ' . number_format($stats->cash_total_amount, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make($cashReceivedLabel, 'Rp ' . number_format($stats->cash_paid_amount, 0, ',', '.'))
                ->description($stats->cash_payment_rate . '% paid')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make($cashOutstandingLabel, 'Rp ' . number_format($stats->cash_outstanding, 0, ',', '.'))
                ->description((100 - $stats->cash_payment_rate) . '% unpaid')
                ->descriptionIcon('heroicon-m-clock')
                ->color($stats->cash_outstanding > 0 ? 'danger' : 'success'),
        ];

        // Add status-based stats dengan breakdown
        foreach ($statusStats as $status => $stat) {
            $statsArray[] = Stat::make($status . ' Services', number_format($stat->count))
                ->description('Total: Rp ' . number_format($stat->total_amount, 0, ',', '.') . ' | Paid: ' . $stat->payment_rate . '%')
                ->descriptionIcon(match($status) {
                    'Requested' => 'heroicon-m-paper-airplane',
                    'Approved' => 'heroicon-m-check-circle',
                    'In Progress' => 'heroicon-m-cog',
                    'Done' => 'heroicon-m-check-badge',
                    default => 'heroicon-m-wrench-screwdriver'
                })
                ->color(match($status) {
                    'Requested' => 'amber',
                    'Approved' => 'blue',
                    'In Progress' => 'purple',
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
        return 'Service Analysis by Type & Status';
    }
}
