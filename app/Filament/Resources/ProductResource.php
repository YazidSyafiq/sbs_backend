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
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\Code;
use Illuminate\Support\Carbon;

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
                            ->live() // Tambahkan live untuk reactive
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // Reset code_id ketika category berubah
                                $set('code_id', null);
                            })
                            ->required(),
                        Forms\Components\Select::make('code_id')
                            ->options(function (Forms\Get $get) {
                                $categoryId = $get('category_id');
                                if (!$categoryId) {
                                    return [];
                                }

                                return Code::where('category_id', $categoryId)
                                    ->where('type', 'Product')
                                    ->pluck('code', 'id')
                                    ->toArray();
                            })
                            ->hidden(fn (Forms\Get $get) => !$get('category_id'))
                            ->searchable()
                            ->preload()
                            ->placeholder('Select Code')
                            ->label('Code')
                            ->required(),
                        Forms\Components\TextInput::make('stock')
                            ->required()
                            ->label('Ready Stock')
                            ->placeholder('Enter Stock')
                            ->hint('Example: 10')
                            ->numeric(),
                        Forms\Components\TextInput::make('supplier_price')
                            ->required()
                            ->numeric()
                            ->label('Cost Price')
                            ->placeholder('Enter Cost Price')
                            ->hint('Example: 10000')
                            ->prefix('Rp'),
                        Forms\Components\TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->label('Selling Price')
                            ->placeholder('Enter Selling Price')
                            ->hint('Example: 10000')
                            ->prefix('Rp'),
                    ]),
                    Forms\Components\Section::make('Product Date Form')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DatePicker::make('entry_date')
                            ->label('Entry Date')
                            ->placeholder('Select Entry Date')
                            ->required(),
                        Forms\Components\DatePicker::make('expiry_date')
                            ->label('Expiry Date')
                            ->placeholder('Select Expiry Date'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expiry Date')
                    ->date()
                    ->formatStateUsing(function ($state) {
                        if (!$state) {
                            return 'No Expiry Date';
                        }

                        return Carbon::parse($state)->format('d M Y');
                    })
                    ->color(function ($record) {
                        if (!$record->expiry_date) return 'gray';

                        $daysUntilExpiry = now()->diffInDays($record->expiry_date, false);

                        if ($daysUntilExpiry < 0) {
                            return 'danger';
                        } elseif ($daysUntilExpiry <= 30) {
                            return 'warning';
                        }

                        return 'success';
                    }),
                Tables\Columns\TextColumn::make('expiry_status')
                    ->label('Expiry Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Expired' => 'danger',
                        'Expiring Soon' => 'warning',
                        'Fresh' => 'success',
                        'No Expiry Date' => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Expired' => 'heroicon-m-x-circle',
                        'Expiring Soon' => 'heroicon-m-exclamation-triangle',
                        'Fresh' => 'heroicon-m-check-circle',
                        'No Expiry Date' => 'heroicon-m-minus-circle',
                    }),
                Tables\Columns\TextColumn::make('supplier_price')
                    ->label('Cost Price')
                    ->numeric()
                    ->formatStateUsing(function ($state) {
                        if (is_null($state)) return '-';
                        return 'Rp ' . number_format($state, 0, ',', '.');
                    }),
                Tables\Columns\TextColumn::make('price')
                    ->label('Selling Price')
                    ->numeric()
                    ->formatStateUsing(function ($state) {
                        if (is_null($state)) return '-';
                        return 'Rp ' . number_format($state, 0, ',', '.');
                    }),
                Tables\Columns\TextColumn::make('display_stock')
                    ->label('Ready Stock')
                    ->numeric(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stock Status')
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
                Tables\Columns\TextColumn::make('need_purchase')
                    ->label('Need Purchase')
                    ->numeric()
                    ->color(fn ($state) => $state > 0 ? 'danger' : null),
                Tables\Columns\TextColumn::make('purchase_status')
                    ->label('Purchase Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Need Purchase' => 'danger',
                        'Stock Available' => 'success',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Need Purchase' => 'heroicon-m-x-circle',
                        'Stock Available' => 'heroicon-m-check-circle',
                    }),
                Tables\Columns\TextColumn::make('on_request')
                    ->label('Request')
                    ->numeric(),
                Tables\Columns\TextColumn::make('on_processing')
                    ->label('Processing')
                    ->color('warning')
                    ->numeric(),
                Tables\Columns\TextColumn::make('on_shipped')
                    ->label('Shipped')
                    ->color('info')
                    ->numeric(),
                Tables\Columns\TextColumn::make('on_received')
                    ->label('Received')
                    ->color('success')
                    ->numeric(),
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
