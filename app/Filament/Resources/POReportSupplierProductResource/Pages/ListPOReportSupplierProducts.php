<?php

namespace App\Filament\Resources\POReportSupplierProductResource\Pages;

use App\Filament\Resources\POReportSupplierProductResource;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\ProductCategory;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use App\Exports\POReportSupplierProductExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Filament\Resources\POReportSupplierProductResource\Widgets\POReportSupplierProductFilterInfo;
use App\Filament\Resources\POReportSupplierProductResource\Widgets\POReportSupplierProductOverview;
use App\Filament\Resources\POReportSupplierProductResource\Widgets\POReportSupplierProductBySupplier;
use App\Filament\Resources\POReportSupplierProductResource\Widgets\POReportSupplierProductByProduct;
use App\Filament\Resources\POReportSupplierProductResource\Widgets\POReportSupplierProductLineChart;
use App\Filament\Resources\POReportSupplierProductResource\Widgets\POReportSupplierProductQuantityChart;
use App\Filament\Resources\POReportSupplierProductResource\Widgets\POReportSupplierProductPOChart;
use App\Filament\Resources\POReportSupplierProductResource\Widgets\POReportSupplierProductDiversityChart;
use Filament\Notifications\Notification;
use App\Models\POReportSupplierProduct;

class ListPOReportSupplierProducts extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = POReportSupplierProductResource::class;

    protected function getHeaderWidgets(): array
    {
        $widgets = [];

        // Only show filter info widget if filters are active
        $filters = session('supplier_product_filters', []);
        $activeFilters = collect($filters)
            ->filter(function($value) {
                if (is_array($value)) {
                    return !empty($value);
                }
                return !is_null($value) && $value !== false && $value !== '';
            });

        if ($activeFilters->count() > 0) {
            $widgets[] = POReportSupplierProductFilterInfo::class;
        }

        // Add other widgets
        $widgets = array_merge($widgets, [
            POReportSupplierProductOverview::class,
            POReportSupplierProductBySupplier::class,
            POReportSupplierProductByProduct::class,
            POReportSupplierProductLineChart::class,
            POReportSupplierProductQuantityChart::class,
            POReportSupplierProductPOChart::class,
            POReportSupplierProductDiversityChart::class,
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
                ->modalHeading('Filter Supplier-Product Analytics')
                ->modalDescription('Apply filters to all widgets and table data')
                ->modalWidth('4xl')
                ->fillForm(function(): array {
                    return session('supplier_product_filters', [
                        'supplier_id' => null,
                        'product_id' => null,
                        'category_id' => null,
                        'type_po' => [],
                        'status' => [],
                        'status_paid' => [],
                        'date_from' => null,
                        'date_until' => null,
                        'outstanding_only' => false,
                    ]);
                })
                ->form([
                    Section::make('Product & Supplier Filters')
                        ->schema([
                            Grid::make(3)
                                ->schema([
                                    Select::make('supplier_id')
                                        ->label('Supplier')
                                        ->options(function() {
                                            return Supplier::select('id', 'name', 'code')
                                                ->get()
                                                ->mapWithKeys(function ($supplier) {
                                                    return [$supplier->id => $supplier->name . ' (' . $supplier->code . ')'];
                                                })
                                                ->toArray();
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select a supplier'),

                                    Select::make('product_id')
                                        ->label('Product')
                                        ->options(function() {
                                            return Product::select('id', 'name', 'code')
                                                ->get()
                                                ->mapWithKeys(function ($product) {
                                                    return [$product->id => $product->name . ' (' . $product->code . ')'];
                                                })
                                                ->toArray();
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select a product'),

                                    Select::make('category_id')
                                        ->label('Product Category')
                                        ->options(ProductCategory::pluck('name', 'id')->toArray())
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select a category'),
                                ]),
                        ]),
                    Section::make('Order & Payment Filters')
                        ->schema([
                            Grid::make(3)
                                ->schema([
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
                                ]),
                        ]),
                    Section::make('Date & Special Filters')
                        ->schema([
                            Grid::make(3)
                                ->schema([
                                    DatePicker::make('date_from')
                                        ->label('From Date')
                                        ->placeholder('Select start date'),

                                    DatePicker::make('date_until')
                                        ->label('Until Date')
                                        ->placeholder('Select end date'),

                                    Toggle::make('outstanding_only')
                                        ->label('Outstanding Payments Only')
                                        ->helperText('Show only unpaid purchase orders'),
                                ]),
                        ]),
                ])
                ->action(function (array $data): void {
                    session(['supplier_product_filters' => $data]);

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
                        'supplier_id' => null,
                        'product_id' => null,
                        'category_id' => null,
                        'type_po' => [],
                        'status' => [],
                        'status_paid' => [],
                        'date_from' => null,
                        'date_until' => null,
                        'outstanding_only' => false,
                    ];

                    session(['supplier_product_filters' => $defaultFilters]);

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
                    $filters = session('supplier_product_filters', []);

                    // Generate filename with current date and filters
                    $filename = 'supplier_product_analytics_' . now()->format('Y-m-d_H-i-s');

                    // Add filter info to filename if filters are applied
                    if (POReportSupplierProduct::hasActiveFilters($filters)) {
                        $filename .= '_filtered';
                    }

                    $filename .= '.xlsx';

                    Notification::make()
                        ->title('Report Generation Started')
                        ->body('Your Excel report is being generated. Download will start shortly.')
                        ->info()
                        ->send();

                    // Note: You'll need to create POReportSupplierProductExport class
                    return Excel::download(new POReportSupplierProductExport($filters), $filename);
                })
                ->visible(function() {
                    // Only show if export class exists
                    try {
                        return class_exists('App\Exports\POReportSupplierProductExport');
                    } catch (Exception $e) {
                        return false;
                    }
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'Supplier Product Analytics';
    }
}
