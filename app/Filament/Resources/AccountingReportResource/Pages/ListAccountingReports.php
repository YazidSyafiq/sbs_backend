<?php

namespace App\Filament\Resources\AccountingReportResource\Pages;

use App\Filament\Resources\AccountingReportResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use App\Exports\AccountingReportExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Filament\Resources\AccountingReportResource\Widgets\AccountingFilterInfo;
use App\Filament\Resources\AccountingReportResource\Widgets\AccountingOverview;
use App\Filament\Resources\AccountingReportResource\Widgets\AccountingDebtAnalysis;
use App\Filament\Resources\AccountingReportResource\Widgets\AccountingRevenueBreakdown;
use App\Filament\Resources\AccountingReportResource\Widgets\AccountingCostBreakdown;
use App\Filament\Resources\AccountingReportResource\Widgets\AccountingProductSummary;
use App\Filament\Resources\AccountingReportResource\Widgets\AccountingServiceSummary;
use App\Filament\Resources\AccountingReportResource\Widgets\AccountingProductSalesAnalysis;
use App\Filament\Resources\AccountingReportResource\Widgets\AccountingServiceSalesAnalysis;
use App\Filament\Resources\AccountingReportResource\Widgets\AccountingProfitChart;
use App\Filament\Resources\AccountingReportResource\Widgets\AccountingOutstandingBalanceChart;
use Filament\Notifications\Notification;
use App\Models\AccountingReport;
use Carbon\Carbon;

class ListAccountingReports extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = AccountingReportResource::class;

    protected function getHeaderWidgets(): array
    {
        $widgets = [];

        // Only show filter info widget if filters are active
        $filters = session('accounting_filters', []);
        $activeFilters = collect($filters)
            ->filter(function($value) {
                if (is_array($value)) {
                    return !empty($value);
                }
                return !is_null($value) && $value !== false && $value !== '';
            });

        if ($activeFilters->count() > 0) {
            $widgets[] = AccountingFilterInfo::class;
        }

        // Add widgets in logical order: Overview → Breakdowns → Details → Charts
        $widgets = array_merge($widgets, [
            // Financial Overview
            AccountingOverview::class,
            AccountingDebtAnalysis::class,

            // Revenue & Cost Breakdown
            AccountingRevenueBreakdown::class,
            AccountingCostBreakdown::class,

            // Product & Service Summaries
            AccountingProductSummary::class,
            AccountingServiceSummary::class,

            // Detailed Individual Performance
            AccountingProductSalesAnalysis::class,
            AccountingServiceSalesAnalysis::class,

            // Charts
            AccountingProfitChart::class,
            AccountingOutstandingBalanceChart::class,
        ]);

        return $widgets;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('filter')
                ->label('Filter Period')
                ->icon('heroicon-o-funnel')
                ->color('primary')
                ->modalHeading('Filter Accounting Report')
                ->modalDescription('Select date range for accounting analysis')
                ->modalWidth('2xl')
                ->fillForm(function(): array {
                    $currentFilters = session('accounting_filters', []);

                    if (empty($currentFilters['date_from']) && empty($currentFilters['date_until'])) {
                        return [
                            'date_from' => Carbon::now()->subMonths(11)->startOfMonth()->toDateString(),
                            'date_until' => Carbon::now()->endOfMonth()->toDateString(),
                        ];
                    }

                    return $currentFilters;
                })
                ->form([
                    Section::make('Date Range')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    DatePicker::make('date_from')
                                        ->label('From Date')
                                        ->placeholder('Select start date')
                                        ->required(),

                                    DatePicker::make('date_until')
                                        ->label('Until Date')
                                        ->placeholder('Select end date')
                                        ->required(),
                                ]),
                        ]),
                ])
                ->action(function (array $data): void {
                    session(['accounting_filters' => $data]);

                    $this->redirect(static::getUrl());

                    Notification::make()
                        ->title('Filters Applied')
                        ->body('Accounting report updated with selected period.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('reset_filters')
                ->label('Reset Filters')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    session(['accounting_filters' => []]);

                    $this->redirect(static::getUrl());

                    Notification::make()
                        ->title('Filters Reset')
                        ->body('Showing last 12 months accounting data.')
                        ->info()
                        ->send();
                }),

            Actions\Action::make('download_report')
                ->label('Download Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function () {
                    $filters = session('accounting_filters', []);

                    // Generate filename with current date and filters
                    $filename = 'accounting_report_' . now()->format('Y-m-d_H-i-s');

                    // Add filter info to filename if filters are applied
                    if (AccountingReport::hasActiveFilters($filters)) {
                        $filename .= '_filtered';
                    }

                    $filename .= '.xlsx';

                    Notification::make()
                        ->title('Report Generation Started')
                        ->body('Your accounting report is being generated. Download will start shortly.')
                        ->info()
                        ->send();

                    return Excel::download(new AccountingReportExport($filters), $filename);
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'Accounting Reports';
    }

    public function mount(): void
    {
        parent::mount();

        if (!session()->has('accounting_filters')) {
            session(['accounting_filters' => []]);
        }
    }
}
