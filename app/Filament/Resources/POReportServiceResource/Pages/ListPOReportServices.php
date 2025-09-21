<?php

namespace App\Filament\Resources\POReportServiceResource\Pages;

use App\Filament\Resources\POReportServiceResource;
use App\Models\Branch;
use App\Models\Technician;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use App\Exports\POReportServiceExport;
use Maatwebsite\Excel\Facades\Excel;
// Basic widgets
use App\Filament\Resources\POReportServiceResource\Widgets\POReportServiceFilterInfo;
use App\Filament\Resources\POReportServiceResource\Widgets\POReportServiceOverview;
use App\Filament\Resources\POReportServiceResource\Widgets\POReportServiceByBranch;
use App\Filament\Resources\POReportServiceResource\Widgets\POReportServiceByType;
use App\Filament\Resources\POReportServiceResource\Widgets\POReportServiceLineChart;
use App\Filament\Resources\POReportServiceResource\Widgets\POReportServiceBarChart;
// Profit widgets (sama seperti PO Product)
use App\Filament\Resources\POReportServiceResource\Widgets\POReportServiceProfitOverview;
use App\Filament\Resources\POReportServiceResource\Widgets\POReportServiceTopServices;
use App\Filament\Resources\POReportServiceResource\Widgets\POReportServiceProfitAmountChart;
use App\Filament\Resources\POReportServiceResource\Widgets\POReportServiceProfitMarginChart;
use App\Filament\Resources\POReportServiceResource\Widgets\POReportServiceProfitByBranch;
// Technician widgets (khusus service)
use App\Filament\Resources\POReportServiceResource\Widgets\POReportServiceTechnicianOverview;
use App\Filament\Resources\POReportServiceResource\Widgets\POReportServiceTechnicianDebt;
use App\Filament\Resources\POReportServiceResource\Widgets\POReportServiceTechnicianPerformance;
use App\Filament\Resources\POReportServiceResource\Widgets\POReportServiceTechnicianChart;
use Filament\Notifications\Notification;
use App\Models\POReportService;
use Auth;

class ListPOReportServices extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = POReportServiceResource::class;

    protected function getHeaderWidgets(): array
    {
        $user = Auth::user();
        $widgets = [];

        // Only show filter info widget if filters are active
        $filters = session('po_service_filters', []);
        $activeFilters = collect($filters)
            ->filter(function($value) {
                if (is_array($value)) {
                    return !empty($value);
                }
                return !is_null($value) && $value !== false && $value !== '';
            });

        if ($activeFilters->count() > 0) {
            $widgets[] = POReportServiceFilterInfo::class;
        }

        // Basic widgets for all users
        $widgets = array_merge($widgets, [
            POReportServiceOverview::class,
            POReportServiceByBranch::class,
            POReportServiceByType::class,
            POReportServiceLineChart::class,
            POReportServiceBarChart::class,
        ]);

        // Profit & Technician analysis widgets - ONLY for Admin, Supervisor, Manager, Super Admin
        if ($user && !$user->hasRole('User')) {
            // Profit widgets
            $widgets = array_merge($widgets, [
                POReportServiceProfitOverview::class,
                POReportServiceTopServices::class,
                POReportServiceProfitByBranch::class,
                POReportServiceProfitAmountChart::class,
                POReportServiceProfitMarginChart::class,
            ]);

            // Technician widgets
            $widgets = array_merge($widgets, [
                POReportServiceTechnicianOverview::class,
                POReportServiceTechnicianDebt::class,
                POReportServiceTechnicianPerformance::class,
                POReportServiceTechnicianChart::class,
            ]);
        }

        return $widgets;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('filter')
                ->label('Filter Data')
                ->icon('heroicon-o-funnel')
                ->color('primary')
                ->modalHeading('Filter Service Purchase Orders')
                ->modalDescription('Apply filters to all widgets and table data')
                ->modalWidth('4xl')
                ->fillForm(function(): array {
                    return session('po_service_filters', [
                        'branch_id' => null,
                        'type_po' => [],
                        'status' => [],
                        'status_paid' => [],
                        'technician_id' => null,
                        'date_from' => null,
                        'date_until' => null,
                        'outstanding_only' => false,
                    ]);
                })
                ->form([
                    Section::make()
                        ->schema([
                            Grid::make(3)
                                ->schema([
                                    Select::make('branch_id')
                                        ->label('Branch')
                                        ->options(Branch::pluck('name', 'id')->toArray())
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select a branch')
                                        ->visible(fn () => !Auth::user()->hasRole('User')),

                                    Select::make('type_po')
                                        ->label('Purchase Type')
                                        ->multiple()
                                        ->options([
                                            'credit' => 'Credit Purchase',
                                            'cash' => 'Cash Purchase',
                                        ])
                                        ->placeholder('Select purchase types'),

                                    Select::make('status')
                                        ->label('Status')
                                        ->multiple()
                                        ->options([
                                            'Requested' => 'Requested',
                                            'Approved' => 'Approved',
                                            'In Progress' => 'In Progress',
                                            'Done' => 'Done',
                                        ])
                                        ->placeholder('Select statuses'),

                                    Select::make('status_paid')
                                        ->label('Payment Status')
                                        ->multiple()
                                        ->options([
                                            'unpaid' => 'Unpaid',
                                            'paid' => 'Paid',
                                        ])
                                        ->placeholder('Select payment statuses'),

                                    Select::make('technician_id')
                                        ->label('Technician')
                                        ->options(Technician::pluck('name', 'id')->toArray())
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select a technician')
                                        ->visible(fn () => !Auth::user()->hasRole('User')),

                                    DatePicker::make('date_from')
                                        ->label('From Date')
                                        ->placeholder('Select start date'),

                                    DatePicker::make('date_until')
                                        ->label('Until Date')
                                        ->placeholder('Select end date'),

                                    Toggle::make('outstanding_only')
                                        ->label('Outstanding Debts Only')
                                        ->helperText('Show only unpaid service purchase orders')
                                        ->columnSpanFull(),
                                ]),
                        ]),
                ])
                ->action(function (array $data): void {
                    session(['po_service_filters' => $data]);

                    $this->redirect(static::getUrl());

                    Notification::make()
                        ->title('Filters Applied')
                        ->body('All widgets and table updated with new filters.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('reset_filters')
                ->label('Reset Filters')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    $defaultFilters = [
                        'branch_id' => null,
                        'type_po' => [],
                        'status' => [],
                        'status_paid' => [],
                        'technician_id' => null,
                        'date_from' => null,
                        'date_until' => null,
                        'outstanding_only' => false,
                    ];

                    session(['po_service_filters' => $defaultFilters]);

                    $this->redirect(static::getUrl());

                    Notification::make()
                        ->title('Filters Reset')
                        ->body('All filters have been cleared.')
                        ->info()
                        ->send();
                }),

            Actions\Action::make('download_report')
                ->label('Download Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function () {
                    $filters = session('po_service_filters', []);

                    // Generate filename with current date and filters
                    $filename = 'po_service_report' . now()->format('Y-m-d_H-i-s');

                    // Add filter info to filename if filters are applied
                    if (POReportService::hasActiveFilters($filters)) {
                        $filename .= '_filtered';
                    }

                    $filename .= '.xlsx';

                    Notification::make()
                        ->title('Report Generation Started')
                        ->body('Your Excel report is being generated. Download will start shortly.')
                        ->info()
                        ->send();

                    return Excel::download(new POReportServiceExport($filters), $filename);
                }),
        ];
    }

    public function getTitle(): string
    {
        $user = Auth::user();
        if ($user && $user->hasRole('User')) {
            return 'Purchase Service Analytics';
        }
        return 'Service Sales Analytics';
    }
}
