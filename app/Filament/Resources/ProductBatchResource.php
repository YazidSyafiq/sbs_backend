<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductBatchResource\Pages;
use App\Models\ProductBatch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductBatchResource extends Resource
{
    protected static ?string $model = ProductBatch::class;

    protected static ?string $navigationIcon = 'heroicon-m-archive-box-arrow-down';

    protected static ?string $navigationGroup = 'Product Management';

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return 'Product Batch';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Product Batches';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Batch Information')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('batch_number')
                            ->label('Batch Number')
                            ->disabled()
                            ->dehydrated()
                            ->required(),
                        Forms\Components\Select::make('product_id')
                            ->relationship('product', 'name')
                            ->label('Product')
                            ->disabled()
                            ->dehydrated()
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->name . ' (' . $record->code . ')')
                            ->required(),
                        Forms\Components\Select::make('purchase_product_supplier_id')
                            ->relationship('purchaseProductSupplier', 'po_number')
                            ->label('PO Number')
                            ->disabled()
                            ->dehydrated()
                            ->required(),
                        Forms\Components\TextInput::make('supplier_name')
                            ->label('Supplier')
                            ->disabled()
                            ->formatStateUsing(function ($record) {
                                if (!$record) return '-';

                                // Akses supplier melalui purchaseProductSupplier relationship
                                $supplier = $record->purchaseProductSupplier?->supplier ?? $record->supplier;

                                if ($supplier) {
                                    return $supplier->name . ' (' . $supplier->code . ')';
                                }

                                return '-';
                            }),
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->minValue(0)
                            ->disabled()
                            ->suffix(fn ($record) => $record->product->unit ?? 'pcs')
                            ->required(),
                        Forms\Components\TextInput::make('cost_price')
                            ->label('Cost Price')
                            ->numeric()
                            ->disabled()
                            ->prefix('Rp')
                            ->required(),
                        Forms\Components\DatePicker::make('entry_date')
                            ->label('Entry Date')
                            ->disabled()
                            ->dehydrated()
                            ->disabled()
                            ->required(),
                        Forms\Components\DatePicker::make('expiry_date')
                            ->label('Expiry Date')
                            ->nullable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('quantity', '>', 0)) // Only show active batches
            ->defaultSort('entry_date', 'desc')
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('batch_number')
                    ->label('Batch Number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->formatStateUsing(fn ($record) =>
                        $record->product
                            ? $record->product->name . ' (' . $record->product->code . ')'
                            : '-'
                    )
                    ->searchable(['product.name', 'product.code']),
                Tables\Columns\TextColumn::make('purchaseProductSupplier.po_number')
                    ->label('PO Number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->formatStateUsing(fn ($record) =>
                        $record->supplier
                            ? $record->supplier->name . ' (' . $record->supplier->code . ')'
                            : '-'
                    )
                    ->searchable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Stock')
                    ->badge()
                    ->color(fn ($record): string => match ($record->stock_status) {
                        'Out of Stock' => 'danger',
                        'Low Stock' => 'warning',
                        'In Stock' => 'success',
                        default => 'gray'
                    })
                    ->formatStateUsing(fn ($state, $record) => number_format($state) . ' ' . ($record->product->unit ?? 'pcs')),
                Tables\Columns\TextColumn::make('cost_price')
                    ->label('Cost Price')
                    ->formatStateUsing(function ($state) {
                        return 'Rp ' . number_format($state, 0, ',', '.');
                    }),
                Tables\Columns\TextColumn::make('entry_date')
                    ->label('Entry Date')
                    ->date(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expiry Date')
                    ->date()
                    ->placeholder('No Expiry')
                    ->badge()
                    ->color(fn ($record): string => match ($record->expiry_status) {
                        'Expired' => 'danger',
                        'Expiring Soon' => 'warning',
                        'Fresh' => 'success',
                        'No Expiry Date' => 'gray',
                        default => 'gray'
                    })
                    ->formatStateUsing(fn ($state, $record) => $state ? $state->format('d M Y') : $record->expiry_status),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_id')
                    ->relationship('product', 'name')
                    ->label('Product')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name . ' (' . $record->code . ')')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('low_stock')
                    ->label('Low Stock')
                    ->query(fn (Builder $query): Builder => $query->where('quantity', '<', 10)->where('quantity', '>', 0)),
                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring Soon')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereNotNull('expiry_date')
                              ->whereDate('expiry_date', '<=', now()->addDays(30))
                              ->whereDate('expiry_date', '>=', now())
                    ),
                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereNotNull('expiry_date')
                              ->whereDate('expiry_date', '<', now())
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
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
            'index' => Pages\ListProductBatches::route('/'),
            'view' => Pages\ViewProductBatch::route('/{record}'),
            'edit' => Pages\EditProductBatch::route('/{record}/edit'),
        ];
    }
}
