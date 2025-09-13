<?php

namespace App\Filament\Resources\ProductAnalyticReportResource\Pages;

use App\Filament\Resources\ProductAnalyticReportResource;
use App\Models\ProductCategory;
use App\Models\Product;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Notifications\Notification;
use App\Models\ProductAnalyticReport;
use App\Filament\Resources\ProductAnalyticReportResource\Widgets\ProductAnalyticsFilterInfo;
use App\Filament\Resources\ProductAnalyticReportResource\Widgets\ProductAnalyticsOverview;
use App\Filament\Resources\ProductAnalyticReportResource\Widgets\ProductAnalyticsByCategory;
use App\Filament\Resources\ProductAnalyticReportResource\Widgets\ProductAnalyticsNeedAttention;
use App\Filament\Resources\ProductAnalyticReportResource\Widgets\ProductAnalyticsEntryTrend;
use App\Filament\Resources\ProductAnalyticReportResource\Widgets\ProductAnalyticsPOStatusBreakdown;

class ListProductAnalyticReports extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = ProductAnalyticReportResource::class;

    protected function getHeaderWidgets(): array
    {
        $widgets = [];

        // Only show filter info widget if filters are active
        $filters = session('product_analytics_filters', []);

        $activeFilters = collect($filters)
            ->filter(function($value, $key) {
                if (is_array($value)) {
                    return !empty($value);
                }
                return !is_null($value) && $value !== '';
            });

        if ($activeFilters->count() > 0) {
            $widgets[] = ProductAnalyticsFilterInfo::class;
        }

        $widgets = array_merge($widgets, [
            ProductAnalyticsOverview::class,
            ProductAnalyticsByCategory::class,
            ProductAnalyticsNeedAttention::class,
            ProductAnalyticsEntryTrend::class,
            ProductAnalyticsPOStatusBreakdown::class,
        ]);

        return $widgets;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('filter')
                ->label('Filter Analytics')
                ->icon('heroicon-o-funnel')
                ->color('primary')
                ->modalHeading('Filter Product Analytics')
                ->modalDescription('Apply filters to all widgets and table data')
                ->modalWidth('3xl')
                ->fillForm(function(): array {
                    return session('product_analytics_filters', [
                        'product_ids' => [],
                        'category_ids' => [],
                        'date_from' => null,
                        'date_until' => null,
                    ]);
                })
                ->form([
                    Section::make('Analytics Filters')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Select::make('product_ids')
                                        ->label('Products')
                                        ->multiple()
                                        ->options(Product::select('id', 'name', 'code')
                                                ->get()
                                                ->mapWithKeys(function ($product) {
                                                    return [$product->id => $product->name . ' (' . $product->code . ')'];
                                                })
                                                ->toArray())
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select specific products'),

                                    Select::make('category_ids')
                                        ->label('Categories')
                                        ->multiple()
                                        ->options(ProductCategory::pluck('name', 'id')->toArray())
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select categories'),

                                    DatePicker::make('date_from')
                                        ->label('Entry Date From')
                                        ->placeholder('Filter by batch entry date'),

                                    DatePicker::make('date_until')
                                        ->label('Entry Date Until')
                                        ->placeholder('Filter by batch entry date'),
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
                        'product_ids' => [],
                        'category_ids' => [],
                        'date_from' => null,
                        'date_until' => null,
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
