<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IncomeResource\Pages;
use App\Filament\Resources\IncomeResource\RelationManagers;
use App\Models\Income;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Textarea;

class IncomeResource extends Resource
{
    protected static ?string $model = Income::class;

    protected static ?string $navigationIcon = 'heroicon-m-currency-dollar';

    protected static ?string $navigationGroup = 'Accounting and Report';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return 'Income';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Incomes';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Income Form')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Income Name')
                            ->placeholder('Enter Income Name')
                            ->hint('Example: Salary, Bonus, etc')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('date')
                            ->label('Income Date')
                            ->required(),
                        Forms\Components\TextInput::make('income_amount')
                            ->required()
                            ->label('Income Amount')
                            ->placeholder('Enter Income Amount')
                            ->hint('Example: 1000')
                            ->prefix('Rp')
                            ->numeric()
                            ->columnSpanFull(),
                        TextArea::make('description')
                            ->label('Income Description')
                            ->placeholder('Enter Income Description')
                            ->columnSpanFull()
                            ->rows(3)
                            ->maxLength(255),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->searchable()
                    ->date(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Income Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('income_amount')
                    ->label('Income Amount')
                    ->numeric()
                    ->color('success')
                    ->formatStateUsing(function ($state) {
                        if (is_null($state)) return '-';
                        return 'Rp ' . number_format($state, 0, ',', '.');
                    })
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('')
                            ->numeric()
                            ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.')),
                    ]),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIncomes::route('/'),
            'create' => Pages\CreateIncome::route('/create'),
            'view' => Pages\ViewIncome::route('/{record}'),
            'edit' => Pages\EditIncome::route('/{record}/edit'),
        ];
    }
}
