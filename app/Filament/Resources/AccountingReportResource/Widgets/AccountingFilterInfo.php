<?php

namespace App\Filament\Resources\AccountingReportResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class AccountingFilterInfo extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('accounting_filters', []);

        $stats = [];

        if (!empty($filters['date_from']) && !empty($filters['date_until'])) {
            $dateFrom = Carbon::parse($filters['date_from']);
            $dateTo = Carbon::parse($filters['date_until']);

            $daysDiff = $dateFrom->diffInDays($dateTo) + 1;

            $stats[] = Stat::make('Reporting Period', $daysDiff . ' days')
                ->description('From ' . $dateFrom->format('d M Y') . ' to ' . $dateTo->format('d M Y'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('blue');
        }

        return $stats;
    }

    protected function getColumns(): int
    {
        return 1;
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
