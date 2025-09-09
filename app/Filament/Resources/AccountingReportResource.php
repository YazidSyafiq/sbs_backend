<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountingReportResource\Pages;
use App\Models\AccountingReport;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;

class AccountingReportResource extends Resource
{
    protected static ?string $model = AccountingReport::class;

    protected static ?string $navigationIcon = 'heroicon-c-calculator';

    protected static ?string $navigationGroup = 'Accounting and Report';

    protected static ?int $navigationSort = 6;

    public static function getModelLabel(): string
    {
        return 'Accounting Report';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Accounting Reports';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period')
                    ->label('Period')
                    ->badge()
                    ->color('primary')
                    ->size('lg')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Total Revenue')
                    ->formatStateUsing(function ($state) {
                        return 'Rp ' . number_format($state, 0, ',', '.');
                    })
                    ->color('success')
                    ->weight('bold')
                    ->size('lg')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->formatStateUsing(function ($state) {
                        return 'Rp ' . number_format($state, 0, ',', '.');
                    })
                    ->color('danger')
                    ->weight('bold')
                    ->size('lg')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('profit')
                    ->label('Profit')
                    ->formatStateUsing(function ($state) {
                        return 'Rp ' . number_format($state, 0, ',', '.');
                    })
                    ->color(fn($record) => $record->profit >= 0 ? 'success' : 'danger')
                    ->weight('bold')
                    ->size('lg')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('profit_margin_percentage')
                    ->label('Profit Margin')
                    ->suffix('%')
                    ->color(fn($record) => $record->profit_margin_percentage >= 0 ? 'success' : 'danger')
                    ->weight('bold')
                    ->size('lg')
                    ->alignEnd(),
            ])
            ->paginated(false)
            ->query(function () {
                // Return virtual single record based on Income table
                return AccountingReport::query()->take(1);
            })
            ->contentGrid([
                'md' => 1,
                'xl' => 1,
            ])
            ->striped(false)
            ->defaultSort('id', 'desc');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Form not needed since we're not doing CRUD operations
            ]);
    }

    // Disable CRUD operations
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }
    public static function canView($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountingReports::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->take(1);
    }
}
