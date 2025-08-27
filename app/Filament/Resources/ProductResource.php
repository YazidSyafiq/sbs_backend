<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-m-shopping-bag';

    protected static ?string $navigationGroup = 'Stock Management';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return 'Product';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Products';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Product Form')
                    ->collapsible()
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Product Name')
                            ->placeholder('Enter Product Name')
                            ->hint('Example: Apple')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Select Category')
                            ->label('Category')
                            ->required(),
                        Forms\Components\TextInput::make('stock')
                            ->required()
                            ->label('Stock')
                            ->placeholder('Enter Stock')
                            ->hint('Example: 10')
                            ->numeric(),
                        Forms\Components\TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->label('Price')
                            ->placeholder('Enter Price')
                            ->hint('Example: 10000')
                            ->prefix('Rp'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable(),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stock')
                    ->numeric(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->numeric()
                    ->formatStateUsing(function ($state) {
                        if (is_null($state)) return '-';
                        return 'Rp ' . number_format($state, 0, ',', '.');
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Out of Stock' => 'danger',
                        'Low Stock' => 'warning',
                        'In Stock' => 'success',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Out of Stock' => 'heroicon-m-x-circle',
                        'Low Stock' => 'heroicon-m-exclamation-triangle',
                        'In Stock' => 'heroicon-m-check-circle',
                    }),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
