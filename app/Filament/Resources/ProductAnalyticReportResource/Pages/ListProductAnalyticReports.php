<?php

namespace App\Filament\Resources\ProductAnalyticReportResource\Pages;

use App\Filament\Resources\ProductAnalyticReportResource;
use App\Models\ProductCategory;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use App\Models\ProductAnalyticReport;
use App\Filament\Resources\ProductAnalyticReportResource\Widgets\ProductAnalyticsFilterInfo;
use App\Filament\Resources\ProductAnalyticReportResource\Widgets\ProductAnalyticsOverview;
use App\Filament\Resources\ProductAnalyticReportResource\Widgets\ProductAnalyticsByCategory;
use App\Filament\Resources\ProductAnalyticReportResource\Widgets\ProductAnalyticsNeedAttention;
use App\Filament\Resources\ProductAnalyticReportResource\Widgets\ProductAnalyticsDistributionChart;
use App\Filament\Resources\ProductAnalyticReportResource\Widgets\ProductAnalyticsEntryTrend;

class ListProductAnalyticReports extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = ProductAnalyticReportResource::class;

    protected function getHeaderWidgets(): array
    {
        $widgets = [];

        // Only show filter info widget if filters are active
        $filters = session('product_analytics_filters', []);

        // Check for truly active filters (excluding default purchase dates)
        $activeFilters = collect($filters)
            ->filter(function($value, $key) {
                // Exclude purchase date filters from active filter detection
                // as they have default values for analysis purposes
                if (in_array($key, ['purchase_date_from', 'purchase_date_until'])) {
                    return false;
                }

                if (is_array($value)) {
                    return !empty($value);
                }
                return !is_null($value) && $value !== false && $value !== '';
            });

        if ($activeFilters->count() > 0) {
            $widgets[] = ProductAnalyticsFilterInfo::class;
        }

        // Updated widgets list
        $widgets = array_merge($widgets, [
            ProductAnalyticsOverview::class,
            ProductAnalyticsByCategory::class,
            ProductAnalyticsNeedAttention::class,
            ProductAnalyticsEntryTrend::class,
            ProductAnalyticsDistributionChart::class,
        ]);

        return $widgets;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('filter')
                ->label('Filter Products')
                ->icon('heroicon-o-funnel')
                ->color('primary')
                ->modalHeading('Filter Product Analytics')
                ->modalDescription('Apply filters to all widgets and table data')
                ->modalWidth('4xl')
                ->fillForm(function(): array {
                    return session('product_analytics_filters', [
                        'category_id' => [],
                        'stock_status' => [],
                        'expiry_status' => [],
                        'entry_date_from' => null,
                        'entry_date_until' => null,
                        'purchase_date_from' => null,
                        'purchase_date_until' => null,
                        'price_min' => null,
                        'price_max' => null,
                        'need_purchase_only' => false,
                    ]);
                })
                ->form([
                    Section::make('Product Filters')
                        ->schema([
                            Grid::make(3)
                                ->schema([
                                    Select::make('category_id')
                                        ->label('Categories')
                                        ->multiple()
                                        ->options(ProductCategory::pluck('name', 'id')->toArray())
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select categories'),

                                    Select::make('stock_status')
                                        ->label('Stock Status')
                                        ->multiple()
                                        ->options([
                                            'out_of_stock' => 'Out of Stock',
                                            'low_stock' => 'Low Stock',
                                            'in_stock' => 'In Stock',
                                        ])
                                        ->placeholder('Select stock statuses'),

                                    Select::make('expiry_status')
                                        ->label('Expiry Status')
                                        ->multiple()
                                        ->options([
                                            'expired' => 'Expired',
                                            'expiring_soon' => 'Expiring Soon (30 days)',
                                            'fresh' => 'Fresh',
                                            'no_expiry' => 'No Expiry Date',
                                        ])
                                        ->placeholder('Select expiry statuses'),

                                    DatePicker::make('entry_date_from')
                                        ->label('Entry Date From')
                                        ->placeholder('Select start date'),

                                    DatePicker::make('entry_date_until')
                                        ->label('Entry Date Until')
                                        ->placeholder('Select end date'),

                                    Toggle::make('need_purchase_only')
                                        ->label('Need Purchase Only')
                                        ->helperText('Show only products that need purchasing'),
                                ]),
                        ]),

                    Section::make('Purchase Analysis Filters')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    DatePicker::make('purchase_date_from')
                                        ->label('Purchase Date From')
                                        ->placeholder('For purchase activity analysis'),

                                    DatePicker::make('purchase_date_until')
                                        ->label('Purchase Date Until')
                                        ->placeholder('For purchase activity analysis'),
                                ]),
                        ]),

                    Section::make('Price Filters')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('price_min')
                                        ->label('Minimum Price')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->placeholder('0'),

                                    TextInput::make('price_max')
                                        ->label('Maximum Price')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->placeholder('999999999'),
                                ]),
                        ]),
                ])
                ->action(function (array $data): void {
                    session(['product_analytics_filters' => $data]);

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
                        'category_id' => [],
                        'stock_status' => [],
                        'expiry_status' => [],
                        'entry_date_from' => null,
                        'entry_date_until' => null,
                        'purchase_date_from' => null,
                        'purchase_date_until' => null,
                        'price_min' => null,
                        'price_max' => null,
                        'need_purchase_only' => false,
                    ];

                    session(['product_analytics_filters' => $defaultFilters]);

                    $this->redirect(static::getUrl());

                    Notification::make()
                        ->title('Filters Reset')
                        ->body('All filters have been cleared.')
                        ->info()
                        ->send();
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'Product Analytics';
    }
}
