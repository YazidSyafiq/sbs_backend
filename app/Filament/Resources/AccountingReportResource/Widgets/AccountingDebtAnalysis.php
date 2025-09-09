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

        return [
            Stat::make('Receivables from Customers', 'Rp ' . number_format($debtAnalysis->receivables_from_customers, 0, ',', '.'))
                ->description('Outstanding payments from customers')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('Debt to Suppliers', 'Rp ' . number_format($debtAnalysis->debt_to_suppliers, 0, ',', '.'))
                ->description('Outstanding payments to suppliers')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('warning'),

            Stat::make('Net Debt Position', 'Rp ' . number_format($debtAnalysis->net_debt_position, 0, ',', '.'))
                ->description($debtAnalysis->net_debt_position > 0 ? 'We owe more than we\'re owed' : 'We\'re owed more than we owe')
                ->descriptionIcon($debtAnalysis->net_debt_position > 0 ? 'heroicon-m-arrow-down' : 'heroicon-m-arrow-up')
                ->color($debtAnalysis->net_debt_position > 0 ? 'danger' : 'success'),
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
}
