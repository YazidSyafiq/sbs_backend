<?php

namespace App\Filament\Resources\AllTransactionResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\AllTransaction;
use Carbon\Carbon;

class AllTransactionFilterInfo extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('all_transaction_filters', []);

        $stats = [];

        // Date Range Info
        if (!empty($filters['date_from']) && !empty($filters['date_until'])) {
            $dateFrom = Carbon::parse($filters['date_from']);
            $dateTo = Carbon::parse($filters['date_until']);

            $daysDiff = $dateFrom->diffInDays($dateTo) + 1;

            $stats[] = Stat::make('Date Range', $daysDiff . ' days')
                ->description('From ' . $dateFrom->format('d M Y') . ' to ' . $dateTo->format('d M Y'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('blue');
        }

        // Transaction Types Filter
        if (!empty($filters['transaction_types'])) {
            $types = $filters['transaction_types'];
            $typeCount = count($types);

            $stats[] = Stat::make('Transaction Types', $typeCount . ' selected')
                ->description(implode(', ', array_slice($types, 0, 3)) . ($typeCount > 3 ? '...' : ''))
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('green');
        }

        // Payment Status Filter
        if (!empty($filters['payment_statuses'])) {
            $statuses = $filters['payment_statuses'];
            $statusCount = count($statuses);

            $stats[] = Stat::make('Payment Status', $statusCount . ' selected')
                ->description(implode(', ', array_slice($statuses, 0, 3)) . ($statusCount > 3 ? '...' : ''))
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('orange');
        }

        // Item Types Filter
        if (!empty($filters['item_types'])) {
            $itemTypes = $filters['item_types'];
            $itemCount = count($itemTypes);

            $stats[] = Stat::make('Item Types', $itemCount . ' selected')
                ->description(implode(', ', array_slice($itemTypes, 0, 3)) . ($itemCount > 3 ? '...' : ''))
                ->descriptionIcon('heroicon-m-tag')
                ->color('purple');
        }

        // Branch Filter
        if (!empty($filters['branch'])) {
            $stats[] = Stat::make('Branch Filter', 'Active')
                ->description($filters['branch'])
                ->descriptionIcon('heroicon-m-building-office')
                ->color('cyan');
        }

        // User Filter
        if (!empty($filters['user'])) {
            $stats[] = Stat::make('User Filter', 'Active')
                ->description($filters['user'])
                ->descriptionIcon('heroicon-m-user')
                ->color('indigo');
        }

        return $stats;
    }

    protected function getColumns(): int
    {
        return count($this->getStats());
    }

    public function getHeading(): ?string
    {
        return 'Applied Filters';
    }

    public static function getSort(): int
    {
        return -1;
    }
}
