<?php

namespace App\Filament\Resources\TechnicianResource\Pages;

use App\Filament\Resources\TechnicianResource;
use App\Models\Technician;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Auth;

class ListTechnicians extends ListRecords
{
    protected static string $resource = TechnicianResource::class;

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
                            Forms\Components\Select::make('technician_id')
                                ->label('Select Technician')
                                ->options(function () {
                                    return Technician::select('id', 'name', 'code')
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(function ($technician) {
                                            return [$technician->id => $technician->name . ' (' . $technician->code . ')'];
                                        })
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->placeholder('All Technicians'),
                            Forms\Components\Select::make('piutang_status')
                                ->label('Payment Status')
                                ->options([
                                    'all' => 'All Status',
                                    'has_piutang' => 'Unpaid Orders Only',
                                    'no_piutang' => 'Paid Orders Only',
                                ])
                                ->default('all')
                                ->placeholder('All Status'),
                            Forms\Components\TextInput::make('min_price')
                                ->label('Minimum Service Fee')
                                ->numeric()
                                ->prefix('Rp')
                                ->placeholder('Enter minimum fee'),
                            Forms\Components\TextInput::make('max_price')
                                ->label('Maximum Service Fee')
                                ->numeric()
                                ->prefix('Rp')
                                ->placeholder('Enter maximum fee'),
                        ])
                ])
                ->action(function (array $data) {
                    // Build query parameters
                    $params = array_filter($data);
                    $queryString = http_build_query($params);

                    // Open in new tab
                    return redirect()->away(route('technician.report') . '?' . $queryString);
                })
                ->modalHeading('Generate Technician Report')
                ->modalSubmitActionLabel('Generate Report')
                ->modalWidth('2xl'),
            Actions\CreateAction::make()
                ->label('Add Technician')
                ->icon('heroicon-s-user-plus'),
        ];
    }
}
