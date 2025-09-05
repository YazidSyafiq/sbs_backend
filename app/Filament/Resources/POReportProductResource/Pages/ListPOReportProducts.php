<?php

namespace App\Filament\Resources\POReportProductResource\Pages;

use App\Filament\Resources\POReportProductResource;
use App\Models\Branch;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use App\Exports\POReportProductExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Filament\Resources\POReportProductResource\Widgets\POReportProductFilterInfo;
use App\Filament\Resources\POReportProductResource\Widgets\POReportProductOverview;
use App\Filament\Resources\POReportProductResource\Widgets\POReportProductByBranch;
use App\Filament\Resources\POReportProductResource\Widgets\POReportProductByType;
use App\Filament\Resources\POReportProductResource\Widgets\POReportProductLineChart;
use App\Filament\Resources\POReportProductResource\Widgets\POReportProductBarChart;
use Filament\Notifications\Notification;
use App\Models\POReportProduct;
use Auth;

class ListPOReportProducts extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = POReportProductResource::class;

    protected function getHeaderWidgets(): array
    {
        $widgets = [];

        // Only show filter info widget if filters are active
        $filters = session('po_product_filters', []);
        $activeFilters = collect($filters)
            ->filter(function($value) {
                if (is_array($value)) {
                    return !empty($value);
                }
                return !is_null($value) && $value !== false && $value !== '';
            });

        if ($activeFilters->count() > 0) {
            $widgets[] = POReportProductFilterInfo::class;
        }

        // Add other widgets
        $widgets = array_merge($widgets, [
            POReportProductOverview::class,
            POReportProductByBranch::class,
            POReportProductByType::class,
            POReportProductLineChart::class,
            POReportProductBarChart::class,
        ]);

        return $widgets;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('filter')
                ->label('Filter Data')
                ->icon('heroicon-o-funnel')
                ->color('primary')
                ->modalHeading('Filter Purchase Orders')
                ->modalDescription('Apply filters to all widgets and table data')
                ->modalWidth('4xl')
                ->fillForm(function(): array {
                    return session('po_product_filters', [
                        'branch_id' => null,
                        'type_po' => [],
                        'status' => [],
                        'status_paid' => [],
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
                                            'Processing' => 'Processing',
                                            'Shipped' => 'Shipped',
                                            'Received' => 'Received',
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

                                    DatePicker::make('date_from')
                                        ->label('From Date')
                                        ->placeholder('Select start date'),

                                    DatePicker::make('date_until')
                                        ->label('Until Date')
                                        ->placeholder('Select end date'),

                                    Toggle::make('outstanding_only')
                                        ->label('Outstanding Debts Only')
                                        ->helperText('Show only unpaid purchase orders')
                                        ->columnSpanFull(),
                                ]),
                        ]),
                ])
                ->action(function (array $data): void {
                    session(['po_product_filters' => $data]);

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
                        'date_from' => null,
                        'date_until' => null,
                        'outstanding_only' => false,
                    ];

                    session(['po_product_filters' => $defaultFilters]);

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
                    $filters = session('po_product_filters', []);

                    // Generate filename with current date and filters
                    $filename = 'po_product_report' . now()->format('Y-m-d_H-i-s');

                    // Add filter info to filename if filters are applied
                    if (POReportProduct::hasActiveFilters($filters)) {
                        $filename .= '_filtered';
                    }

                    $filename .= '.xlsx';

                    Notification::make()
                        ->title('Report Generation Started')
                        ->body('Your Excel report is being generated. Download will start shortly.')
                        ->info()
                        ->send();

                    return Excel::download(new POReportProductExport($filters), $filename);
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'Purchase Product Reports';
    }

    // public function getSubheading(): ?string
    // {
    //     $filters = session('po_product_filters', []);
    //     $activeFilters = collect($filters)
    //         ->filter(function($value) {
    //             if (is_array($value)) {
    //                 return !empty($value);
    //             }
    //             return !is_null($value) && $value !== false && $value !== '';
    //         });

    //     if ($activeFilters->count() > 0) {
    //         $filterNames = [];

    //         if (!empty($filters['branch_id'])) {
    //             $branchName = Branch::find($filters['branch_id'])->name ?? 'Unknown Branch';
    //             $filterNames[] = "Branch: {$branchName}";
    //         }

    //         if (!empty($filters['type_po'])) {
    //             $filterNames[] = "Type: " . implode(', ', $filters['type_po']);
    //         }

    //         if (!empty($filters['status'])) {
    //             $filterNames[] = "Status: " . implode(', ', $filters['status']);
    //         }

    //         if (!empty($filters['status_paid'])) {
    //             $filterNames[] = "Payment: " . implode(', ', $filters['status_paid']);
    //         }

    //         if (!empty($filters['date_from']) || !empty($filters['date_until'])) {
    //             $dateRange = "Date: ";
    //             if (!empty($filters['date_from'])) {
    //                 $dateRange .= "from " . date('d M Y', strtotime($filters['date_from']));
    //             }
    //             if (!empty($filters['date_until'])) {
    //                 $dateRange .= " to " . date('d M Y', strtotime($filters['date_until']));
    //             }
    //             $filterNames[] = $dateRange;
    //         }

    //         if (!empty($filters['outstanding_only'])) {
    //             $filterNames[] = "Outstanding debts only";
    //         }

    //         return 'Active filters: ' . implode(' | ', $filterNames) . '. Use Filter Data button to modify.';
    //     }

    //     return 'Use the Filter Data button above to filter data across all widgets and table.';
    // }
}
