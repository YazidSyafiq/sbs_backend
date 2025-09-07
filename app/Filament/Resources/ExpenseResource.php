<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Filament\Resources\ExpenseResource\RelationManagers;
use App\Models\Expense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Textarea;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-m-credit-card';

    protected static ?string $navigationGroup = 'Accounting and Report';

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return 'Expense';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Expenses';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Expense Form')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Expense Name')
                            ->placeholder('Enter Expense Name')
                            ->hint('Example: Salary, Bonus, etc')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('date')
                            ->label('Expense Date')
                            ->required(),
                        Forms\Components\TextInput::make('expense_amount')
                            ->required()
                            ->label('Expense Amount')
                            ->placeholder('Enter Expense Amount')
                            ->hint('Example: 1000')
                            ->prefix('Rp')
                            ->numeric()
                            ->columnSpanFull(),
                        TextArea::make('description')
                            ->label('Expense Description')
                            ->placeholder('Enter Expense Description')
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
                    ->label('Expense Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('expense_amount')
                    ->label('Expense Amount')
                    ->numeric()
                    ->color('danger')
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
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'view' => Pages\ViewExpense::route('/{record}'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
