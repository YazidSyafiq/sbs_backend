<?php

namespace App\Filament\Resources\ServicePurchaseResource\Pages;

use App\Filament\Resources\ServicePurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use App\Models\Service;
use App\Models\Technician;
use Auth;

class ListServicePurchases extends ListRecords
{
    protected static string $resource = ServicePurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_report')
                ->label('Generate Report')
                ->hidden(Auth::user()->hasRole('User'))
                ->icon('heroicon-o-document-chart-bar')
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
                            Forms\Components\Select::make('technician_id')
                                ->label('Technician')
                                ->options(function () {
                                    return Technician::select('id', 'name', 'code')
                                        ->get()
                                        ->mapWithKeys(function ($technician) {
                                            return [$technician->id => $technician->name . ' (' . $technician->code . ')'];
                                        })
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->placeholder('All Technicians'),
                            Forms\Components\Select::make('service_id')
                                ->label('Service')
                                ->options(function () {
                                    return Service::select('id', 'name', 'code')
                                        ->get()
                                        ->mapWithKeys(function ($service) {
                                            return [$service->id => $service->name . ' (' . $service->code . ')'];
                                        })
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->placeholder('All Services'),
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
                                    'Draft' => 'Draft',
                                    'Requested' => 'Requested',
                                    'Approved' => 'Approved',
                                    'In Progress' => 'In Progress',
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
                    return redirect()->away(route('purchase-service.report') . '?' . $queryString);
                })
                ->modalHeading('Generate Service Sales Report')
                ->modalSubmitActionLabel('Generate Report')
                ->modalWidth('2xl'),
            Actions\CreateAction::make()
                ->label('Purchase Order')
                ->hidden(!Auth::user()->hasRole('User'))
                ->icon('heroicon-m-shopping-cart'),
        ];
    }
}
