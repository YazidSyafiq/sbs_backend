<?php

namespace App\Filament\Resources\PurchaseProductSupplierResource\Pages;

use App\Filament\Resources\PurchaseProductSupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use App\Models\Product;
use App\Models\Supplier;
use Auth;

class ListPurchaseProductSuppliers extends ListRecords
{
    protected static string $resource = PurchaseProductSupplierResource::class;

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
                            Forms\Components\Select::make('supplier_id')
                                ->label('Supplier')
                                ->options(function () {
                                    return Supplier::select('id', 'name', 'code')
                                        ->get()
                                        ->mapWithKeys(function ($supplier) {
                                            return [$supplier->id => $supplier->name . ' (' . $supplier->code . ')'];
                                        })
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->placeholder('All Suppliers'),
                            Forms\Components\Select::make('product_id')
                                ->label('Product')
                                ->options(function () {
                                    return Product::select('id', 'name', 'code')
                                        ->get()
                                        ->mapWithKeys(function ($product) {
                                            return [$product->id => $product->name . ' (' . $product->code . ')'];
                                        })
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->placeholder('All Products'),
                            Forms\Components\Select::make('type_po')
                                ->label('Type Purchase')
                                ->options([
                                    'credit' => 'Credit Purchase',
                                    'cash' => 'Cash Purchase',
                                ])
                                ->placeholder('All Types'),
                            Forms\Components\Select::make('status_paid')
                                ->label('Payment Status')
                                ->options([
                                    'unpaid' => 'Unpaid',
                                    'paid' => 'Paid',
                                ])
                                ->placeholder('All Payment Status'),
                            Forms\Components\Select::make('status')
                                ->label('PO Status')
                                ->options([
                                    'Requested' => 'Requested',
                                    'Processing' => 'Processing',
                                    'Received' => 'Received',
                                    'Done' => 'Done',
                                    'Cancelled' => 'Cancelled',
                                ])
                                ->placeholder('All Status')
                                ->columnSpanFull(),
                        ])
                ])
                ->action(function (array $data) {
                    // Build query parameters
                    $params = array_filter($data);
                    $queryString = http_build_query($params);

                    // Open in new tab
                    return redirect()->away(route('purchase-product-supplier.report') . '?' . $queryString);
                })
                ->modalHeading('Generate Purchase Product Supplier Report')
                ->modalSubmitActionLabel('Generate Report')
                ->modalWidth('2xl'),
            Actions\CreateAction::make()
                ->label('Purchase Order')
                ->icon('heroicon-m-shopping-cart'),
        ];
    }
}
