<?php

namespace App\Filament\Resources\AccountingReportResource\Widgets;

use App\Models\AccountingReport;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountingDebtAnalysis extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('accounting_filters', []);
        $debtAnalysis = AccountingReport::getDebtAnalysis($filters);

        // Tentukan status dan warna untuk Net Position
        $netPositionStatus = $debtAnalysis->net_debt_position >= 0
            ? 'We\'re owed more than we owe'
            : 'We owe more than we\'re owed';

        $netPositionIcon = $debtAnalysis->net_debt_position >= 0
            ? 'heroicon-m-arrow-up'
            : 'heroicon-m-arrow-down';

        $netPositionColor = $debtAnalysis->net_debt_position >= 0
            ? 'success'
            : 'danger';

        // Format angka net position dengan tanda + atau -
        $netPositionFormatted = ($debtAnalysis->net_debt_position >= 0 ? '+' : '') .
                               'Rp ' . number_format(abs($debtAnalysis->net_debt_position), 0, ',', '.');

        return [
            Stat::make('Receivables from Customers', 'Rp ' . number_format($debtAnalysis->receivables_from_customers, 0, ',', '.'))
                ->description('Outstanding payments from customers (Products + Services)')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('Debt to Suppliers', 'Rp ' . number_format($debtAnalysis->debt_to_suppliers, 0, ',', '.'))
                ->description('Outstanding payments to suppliers')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('warning'),

            Stat::make('Net Position', $netPositionFormatted)
                ->description($netPositionStatus)
                ->descriptionIcon($netPositionIcon)
                ->color($netPositionColor),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        return 'Debt & Receivables Analysis';
    }

    public function getDescription(): ?string
    {
        return 'Analysis of outstanding payments - what customers owe us vs what we owe suppliers';
    }
}
