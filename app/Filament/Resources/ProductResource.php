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
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
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
                            ->live()
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

                // Active Product Batches Section - Hidden on create, only show active batches
                Forms\Components\Section::make('Active Product Batches')
                    ->hidden(fn (string $context) => $context !== 'view')
                    ->schema([
                        Forms\Components\Repeater::make('activeProductBatches')
                            ->relationship('activeProductBatches') // Changed to active batches relationship
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
                Tables\Columns\TextColumn::make('total_stock')
                    ->label('Current Stock')
                    ->badge()
                    ->color(function ($record) {
                        $currentStock = $record->total_stock;
                        $pendingOrders = $record->pending_orders;

                        if ($pendingOrders > $currentStock) {
                            return 'danger';
                        } elseif ($currentStock <= 0) {
                            return 'danger';
                        } elseif ($currentStock < 10) {
                            return 'warning';
                        } else {
                            return 'success';
                        }
                    })
                    ->formatStateUsing(fn ($state, $record) => number_format($state) . ' ' . ($record->unit ?? 'pcs')),
                Tables\Columns\TextColumn::make('pending_orders')
                    ->label('Pending Orders')
                    ->badge()
                    ->color(function ($record) {
                        $currentStock = $record->total_stock;
                        $pendingOrders = $record->pending_orders;

                        if ($pendingOrders > $currentStock) {
                            return 'danger';
                        } elseif ($pendingOrders > 0) {
                            return 'warning';
                        } else {
                            return 'gray';
                        }
                    })
                    ->formatStateUsing(fn ($state, $record) => number_format($state) . ' ' . ($record->unit ?? 'pcs')),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stock Status')
                    ->badge()
                    ->color(fn ($record) => match ($record->status) {
                        'Out of Stock' => 'danger',
                        'Critical - Orders Exceed Stock' => 'danger',
                        'Low Stock' => 'warning',
                        'In Stock' => 'success',
                        default => 'gray'
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Out of Stock' => 'heroicon-m-x-circle',
                        'Low Stock' => 'heroicon-m-exclamation-triangle',
                        'Critical - Orders Exceed Stock' => 'heroicon-m-exclamation-triangle',
                        'In Stock' => 'heroicon-m-check-circle',
                    }),
                Tables\Columns\TextColumn::make('need_purchase')
                    ->label('Need Purchase')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state > 50 => 'danger',
                        $state > 20 => 'warning',
                        $state > 0 => 'info',
                        default => 'success'
                    })
                    ->formatStateUsing(function ($state, $record) {
                        if ($state <= 0) return 'No';
                        return number_format($state) . ' ' . ($record->unit ?? 'pcs');
                    }),
                Tables\Columns\TextColumn::make('activeProductBatches_count')
                    ->label('Active Batches')
                    ->badge()
                    ->counts('activeProductBatches')
                    ->getStateUsing(fn ($record) => $record->activeProductBatches->count() . ' active')
                    ->color('info'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Category')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('out_of_stock')
                    ->label('Out of Stock')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereDoesntHave('productBatches', function ($q) {
                            $q->where('quantity', '>', 0);
                        })
                    ),
                Tables\Filters\Filter::make('low_stock')
                    ->label('Low Stock')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('productBatches')
                            ->get()
                            ->filter(function ($product) {
                                return $product->available_stock > 0 && $product->available_stock < 10;
                            })
                            ->pluck('id');
                    }),
                Tables\Filters\Filter::make('need_purchase')
                    ->label('Need Purchase')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('productBatches')
                            ->get()
                            ->filter(function ($product) {
                                return $product->need_purchase > 0;
                            })
                            ->pluck('id');
                    }),
                Tables\Filters\Filter::make('critical_stock')
                    ->label('Critical Stock')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('productBatches')
                            ->get()
                            ->filter(function ($product) {
                                $availableStock = $product->available_stock;
                                $pendingOrders = $product->pending_orders;
                                return ($availableStock - $pendingOrders) <= 0 && $availableStock > 0;
                            })
                            ->pluck('id');
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
