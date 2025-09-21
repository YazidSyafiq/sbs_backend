<?php

namespace App\Filament\Resources\PurchaseProductResource\Pages;

use App\Filament\Resources\PurchaseProductResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Auth;

class ListPurchaseProducts extends ListRecords
{
    protected static string $resource = PurchaseProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_report')
                ->label('Generate Report')
                ->icon('heroicon-o-document-chart-bar')
                ->hidden(Auth::user()->hasRole('User'))
                ->color('info')
                ->form([
                    Forms\Components\Section::make('Filter Report')
                        ->columns(2)
                        ->schema([
                            Forms\Components\DatePicker::make('from_date')
                                ->label('From Date')
                                ->required()
                                ->default(now()->startOfMonth()),
                            Forms\Components\DatePicker::make('until_date')
                                ->label('Until Date')
                                ->required()
                                ->default(now()),
                            Forms\Components\Select::make('product_id')
                                ->label('Select Product')
                                ->options(function () {
                                    return Product::select('id', 'name', 'code')
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(function ($product) {
                                            return [$product->id => $product->name . ' (' . $product->code . ')'];
                                        })
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->placeholder('All Products'),
                            Forms\Components\Select::make('piutang_status')
                                ->label('Payment Status')
                                ->options([
                                    'all' => 'All Status',
                                    'has_piutang' => 'Unpaid Orders Only',
                                    'no_piutang' => 'Paid Orders Only',
                                ])
                                ->default('all')
                                ->placeholder('All Status'),
                            Forms\Components\Select::make('type_po')
                                ->label('Type Purchase')
                                ->options([
                                    'credit' => 'Credit Purchase',
                                    'cash' => 'Cash Purchase',
                                ])
                                ->placeholder('All Types'),
                            Forms\Components\Select::make('status')
                                ->label('PO Status')
                                ->options([
                                    'Draft' => 'Draft',
                                    'Requested' => 'Requested',
                                    'Processing' => 'Processing',
                                    'Shipped' => 'Shipped',
                                    'Received' => 'Received',
                                    'Done' => 'Done',
                                    'Cancelled' => 'Cancelled',
                                ])
                                ->placeholder('All Status'),
                            Forms\Components\TextInput::make('min_total_amount')
                                ->label('Minimum Total Amount')
                                ->numeric()
                                ->prefix('Rp')
                                ->placeholder('Enter minimum amount'),
                            Forms\Components\TextInput::make('max_total_amount')
                                ->label('Maximum Total Amount')
                                ->numeric()
                                ->prefix('Rp')
                                ->placeholder('Enter maximum amount'),
                        ])
                ])
                ->action(function (array $data) {
                    // Build query parameters
                    $params = array_filter($data);
                    $queryString = http_build_query($params);

                    // Open in new tab
                    return redirect()->away(route('purchase-product.report') . '?' . $queryString);
                })
                ->modalHeading('Generate Product Sales Report')
                ->modalSubmitActionLabel('Generate Report')
                ->modalWidth('2xl'),
            Actions\CreateAction::make()
                ->label('Purchase Order')
                ->icon('heroicon-m-shopping-cart'),
        ];
    }
}
