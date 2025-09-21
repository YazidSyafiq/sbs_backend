<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use App\Models\ProductCategory;
use Auth;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_report')
                ->label('Generate Report')
                ->icon('heroicon-o-document-chart-bar')
                ->color('info')
                ->form([
                    Forms\Components\Section::make('Filter Report')
                        ->columns(2)
                        ->schema([
                            Forms\Components\Select::make('category_id')
                                ->label('Category')
                                ->options(function () {
                                    return ProductCategory::select('id', 'name')
                                        ->get()
                                        ->mapWithKeys(function ($category) {
                                            return [$category->id => $category->name];
                                        })
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->placeholder('All Categories'),
                            Forms\Components\Select::make('batch_display')
                                ->label('Batch Display')
                                ->options([
                                    'all' => 'All Batches',
                                    'active' => 'Active Batches Only (Stock > 0)',
                                ])
                                ->default('all'),
                            Forms\Components\Select::make('stock_status')
                                ->label('Product Stock Status')
                                ->options([
                                    'in_stock' => 'In Stock (> 10 units)',
                                    'low_stock' => 'Low Stock (1-10 units)',
                                    'out_of_stock' => 'Out of Stock (0 units)',
                                ])
                                ->placeholder('All Product Stock Status'),
                            Forms\Components\Select::make('expiry_filter')
                                ->label('Batch Expiry Status')
                                ->options([
                                    'fresh' => 'Fresh',
                                    'expiring_soon' => 'Expiring Soon (≤30 days)',
                                    'expired' => 'Expired',
                                    'no_expiry' => 'No Expiry Date',
                                ])
                                ->placeholder('All Expiry Status'),
                            Forms\Components\TextInput::make('min_stock')
                                ->label('Minimum Product Stock')
                                ->placeholder('Enter minimum total stock')
                                ->numeric()
                                ->minValue(0)
                                ->hint('Filter by total product stock'),
                            Forms\Components\TextInput::make('max_stock')
                                ->label('Maximum Product Stock')
                                ->placeholder('Enter maximum total stock')
                                ->numeric()
                                ->minValue(0)
                                ->hint('Filter by total product stock'),
                            Forms\Components\TextInput::make('min_price')
                                ->label('Minimum Price')
                                ->placeholder('Enter minimum price')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('Rp'),
                            Forms\Components\TextInput::make('max_price')
                                ->label('Maximum Price')
                                ->placeholder('Enter maximum price')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('Rp'),
                            Forms\Components\Select::make('sort_by')
                                ->label('Sort Batches By')
                                ->options([
                                    'batch_number' => 'Batch Number',
                                    'entry_date' => 'Entry Date (Newest First)',
                                    'expiry_date' => 'Expiry Date (Earliest First)',
                                    'stock' => 'Batch Stock Level (Highest First)',
                                    'cost_price' => 'Cost Price (Highest First)',
                                ])
                                ->default('batch_number')
                                ->columnSpanFull(),
                        ])
                ])
                ->action(function (array $data) {
                    // Build query parameters
                    $params = array_filter($data);
                    $queryString = http_build_query($params);

                    // Open in new tab
                    return redirect()->away(route('product.report') . '?' . $queryString);
                })
                ->modalHeading('Generate Product Batch Report')
                ->modalSubmitActionLabel('Generate Report')
                ->modalWidth('2xl'),
            Actions\CreateAction::make()
                ->label('Add Product')
                ->icon('heroicon-s-plus-circle'),
        ];
    }
}
