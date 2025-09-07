<?php

namespace App\Filament\Resources\ServiceAnalyticReportResource\Pages;

use App\Filament\Resources\ServiceAnalyticReportResource;
use App\Models\ProductCategory;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use App\Filament\Resources\ServiceAnalyticReportResource\Widgets\ServiceAnalyticsFilterInfo;
use App\Filament\Resources\ServiceAnalyticReportResource\Widgets\ServiceAnalyticsOverview;
use App\Filament\Resources\ServiceAnalyticReportResource\Widgets\ServiceAnalyticsByCategory;
use App\Filament\Resources\ServiceAnalyticReportResource\Widgets\ServiceAnalyticsDistributionChart;

class ListServiceAnalyticReports extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = ServiceAnalyticReportResource::class;

    protected function getHeaderWidgets(): array
    {
        $widgets = [];

        // Only show filter info widget if filters are active
        $filters = session('service_analytics_filters', []);

        // Check for truly active filters
        $activeFilters = collect($filters)
            ->filter(function($value, $key) {
                if (is_array($value)) {
                    return !empty($value);
                }
                return !is_null($value) && $value !== false && $value !== '';
            });

        if ($activeFilters->count() > 0) {
            $widgets[] = ServiceAnalyticsFilterInfo::class;
        }

        $widgets = array_merge($widgets, [
            ServiceAnalyticsOverview::class,
            ServiceAnalyticsByCategory::class,
            ServiceAnalyticsDistributionChart::class,
        ]);

        return $widgets;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('filter')
                ->label('Filter Services')
                ->icon('heroicon-o-funnel')
                ->color('primary')
                ->modalHeading('Filter Service Analytics')
                ->modalDescription('Apply filters to all widgets and table data')
                ->modalWidth('3xl')
                ->fillForm(function(): array {
                    return session('service_analytics_filters', [
                        'category_id' => [],
                        'price_min' => null,
                        'price_max' => null,
                    ]);
                })
                ->form([
                    Section::make('Service Filters')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Select::make('category_id')
                                        ->label('Categories')
                                        ->multiple()
                                        ->options(ProductCategory::pluck('name', 'id')->toArray())
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select categories'),

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
                        ]),
                ])
                ->action(function (array $data): void {
                    session(['service_analytics_filters' => $data]);

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
                        'price_min' => null,
                        'price_max' => null,
                    ];

                    session(['service_analytics_filters' => $defaultFilters]);

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
        return 'Service Analytics';
    }
}
