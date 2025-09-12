<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\Code;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-m-shopping-bag';

    protected static ?string $navigationGroup = 'Product Management';

    protected static ?int $navigationSort = 9;

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
                Forms\Components\Section::make('Product Information')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Product Name')
                            ->placeholder('Enter Product Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Select Category')
                            ->label('Category')
                            ->live() // Tambahkan live untuk reactive
                            ->afterStateUpdated(function ($state, Set $set) {
                                // Reset code_id ketika category berubah
                                $set('show_code', '1');
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
                            ->hidden(fn (Forms\Get $get, string $context) => !$get('category_id') || $context === 'view')
                            ->searchable()
                            ->preload()
                            ->placeholder('Select Code')
                            ->live() // Tambahkan live untuk reactive
                            ->label('Code')
                            ->required(),
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->label('Code')
                            ->hidden(fn (string $context) => $context !== 'view')
                            ->placeholder('Enter Code Name'),
                        Forms\Components\TextInput::make('unit')
                            ->label('Unit')
                            ->placeholder('Enter Unit')
                            ->default('pcs')
                            ->hint('Example: pcs, kg, etc')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->columnSpan(function (Get $get, string $context) {
                                return $get('show_code') === null &&  $get('code_id') === null && $context !== 'view' ? 1 : 'full';
                            })
                            ->label('Selling Price')
                            ->placeholder('Enter Selling Price')
                            ->hint('Example: 10000')
                            ->prefix('Rp'),
                    ]),

                // Product Batches Section - Hidden on create
                Forms\Components\Section::make('Product Batches')
                    ->hidden(fn (string $context) => $context !== 'view')
                    ->schema([
                        Forms\Components\Repeater::make('productBatches')
                            ->relationship('productBatches')
                            ->label('')
                            ->disabled()
                            ->columns(3)
                            ->schema([
                                Forms\Components\Select::make('purchase_product_supplier_id')
                                    ->relationship('purchaseProductSupplier', 'po_number')
                                    ->label('PO Number')
                                    ->disabled(),
                                Forms\Components\TextInput::make('supplier_display')
                                    ->label('Supplier')
                                    ->disabled()
                                    ->formatStateUsing(function ($record) {
                                        return $record && $record->supplier
                                            ? $record->supplier->name . ' (' . $record->supplier->code . ')'
                                            : '-';
                                    }),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Stock')
                                    ->disabled()
                                    ->suffix(function (Forms\Get $get) {
                                        return $get('../../unit') ?? 'pcs';
                                    }),
                                Forms\Components\TextInput::make('cost_price')
                                    ->label('Cost Price')
                                    ->disabled()
                                    ->prefix('Rp')
                                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', '.')),
                                Forms\Components\TextInput::make('entry_date')
                                    ->label('Entry Date')
                                    ->disabled()
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : '-'),
                                Forms\Components\TextInput::make('expiry_date')
                                    ->label('Expiry Date')
                                    ->disabled()
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : 'No Expiry'),
                                ])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['batch_number'] ?? null),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
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
                Tables\Columns\TextColumn::make('average_cost_price')
                    ->label('Avg. Cost Price')
                    ->formatStateUsing(function ($state) {
                        if (!$state || $state <= 0) return '-';
                        return 'Rp ' . number_format($state, 0, ',', '.');
                    }),
                Tables\Columns\TextColumn::make('price')
                    ->label('Selling Price')
                    ->formatStateUsing(function ($state) {
                        if (is_null($state)) return '-';
                        return 'Rp ' . number_format($state, 0, ',', '.');
                    }),
                Tables\Columns\TextColumn::make('potential_profit_margin')
                    ->label('Profit Margin')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state >= 50 => 'success',
                        $state >= 25 => 'warning',
                        $state >= 0 => 'info',
                        default => 'danger'
                    })
                    ->formatStateUsing(function ($state) {
                        if (!$state && $state !== 0) return '-';
                        return number_format($state, 1) . '%';
                    }),
                Tables\Columns\TextColumn::make('total_stock')
                    ->label('Total Stock')
                    ->badge()
                    ->color(fn ($record): string => match ($record->status) {
                        'Out of Stock' => 'danger',
                        'Low Stock' => 'warning',
                        'In Stock' => 'success',
                        default => 'gray'
                    })
                    ->formatStateUsing(fn ($state, $record) => number_format($state) . ' ' . ($record->unit ?? 'pcs')),
                Tables\Columns\TextColumn::make('available_stock')
                    ->label('Available Stock')
                    ->badge()
                    ->color(fn ($record): string => match ($record->status) {
                        'Out of Stock' => 'danger',
                        'Low Stock' => 'warning',
                        'In Stock' => 'success',
                        default => 'gray'
                    })
                    ->formatStateUsing(fn ($state, $record) => number_format($state) . ' ' . ($record->unit ?? 'pcs')),
                Tables\Columns\TextColumn::make('productBatches_count')
                    ->label('Batches')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->productBatches->count() . ' batches')
                    ->color('info'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Category')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('low_stock')
                    ->label('Low Stock')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('productBatches', function ($q) {
                            $q->havingRaw('SUM(quantity) < 10');
                        });
                    }),
                Tables\Filters\Filter::make('out_of_stock')
                    ->label('Out of Stock')
                    ->query(function (Builder $query): Builder {
                        return $query->whereDoesntHave('productBatches', function ($q) {
                            $q->where('quantity', '>', 0);
                        });
                    }),
                Tables\Filters\Filter::make('high_profit')
                    ->label('High Profit (≥50%)')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('productBatches', function ($q) {
                            $q->where('quantity', '>', 0);
                        })->get()->filter(function ($product) {
                            return $product->potential_profit_margin >= 50;
                        })->pluck('id');
                    }),
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
