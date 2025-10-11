<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use App\Models\Supplier;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Auth;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

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
                                ->label('Select Supplier')
                                ->options(function () {
                                    return Supplier::select('id', 'name', 'code')
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(function ($supplier) {
                                            return [$supplier->id => $supplier->name . ' (' . $supplier->code . ')'];
                                        })
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->placeholder('All Suppliers'),
                            Forms\Components\Select::make('piutang_status')
                                ->label('Payment Status')
                                ->options([
                                    'all' => 'All Status',
                                    'has_piutang' => 'Unpaid Orders Only',
                                    'no_piutang' => 'Paid Orders Only',
                                ])
                                ->default('all')
                                ->placeholder('All Status'),
                            Forms\Components\TextInput::make('min_total_po')
                                ->label('Minimum Total PO')
                                ->numeric()
                                ->prefix('Rp')
                                ->placeholder('Enter minimum amount'),
                            Forms\Components\TextInput::make('max_total_po')
                                ->label('Maximum Total PO')
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
                    return redirect()->away(route('supplier.report') . '?' . $queryString);
                })
                ->modalHeading('Generate Supplier Report')
                ->modalSubmitActionLabel('Generate Report')
                ->modalWidth('2xl'),
            Actions\CreateAction::make()
                ->label('Add Supplier')
                ->icon('heroicon-s-user-plus'),
        ];
    }
}
