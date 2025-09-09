<?php

namespace App\Filament\Resources\AccountingReportResource\Widgets;

use App\Models\AccountingReport;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountingCashFlowAnalysis extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('accounting_filters', []);
        $cashFlow = AccountingReport::getCashFlowAnalysis($filters);

        return [
            Stat::make('Actual Cash In', 'Rp ' . number_format($cashFlow->actual_cash_in, 0, ',', '.'))
                ->description('Cash received this period')
                ->descriptionIcon('heroicon-m-arrow-down-left')
                ->color('success'),

            Stat::make('Actual Cash Out', 'Rp ' . number_format($cashFlow->actual_cash_out, 0, ',', '.'))
                ->description('Cash spent this period')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->color('danger'),

            Stat::make('Net Cash Flow', 'Rp ' . number_format($cashFlow->net_actual_cash_flow, 0, ',', '.'))
                ->description($cashFlow->net_actual_cash_flow >= 0 ? 'Positive cash flow' : 'Negative cash flow')
                ->descriptionIcon($cashFlow->net_actual_cash_flow >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($cashFlow->net_actual_cash_flow >= 0 ? 'success' : 'danger'),

            Stat::make('Outstanding Receivables', 'Rp ' . number_format($cashFlow->outstanding_receivables, 0, ',', '.'))
                ->description('Money owed to us')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),

            Stat::make('Outstanding Payables', 'Rp ' . number_format($cashFlow->outstanding_payables, 0, ',', '.'))
                ->description('Money we owe')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),

            Stat::make('Net Outstanding Balance', 'Rp ' . number_format($cashFlow->net_outstanding_balance, 0, ',', '.'))
                ->description($cashFlow->net_outstanding_balance >= 0 ? 'Net receivable position' : 'Net payable position')
                ->descriptionIcon($cashFlow->net_outstanding_balance >= 0 ? 'heroicon-m-plus' : 'heroicon-m-minus')
                ->color($cashFlow->net_outstanding_balance >= 0 ? 'success' : 'danger'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        $filters = session('accounting_filters', []);
        return 'Cash Flow Analysis (' . AccountingReport::getPeriodLabel($filters) . ')';
    }
}
