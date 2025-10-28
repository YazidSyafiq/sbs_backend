<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseProductSupplierResource\Pages;
use App\Filament\Resources\PurchaseProductSupplierResource\RelationManagers;
use App\Models\PurchaseProductSupplier;
use App\Models\Supplier;
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
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Auth;

class PurchaseProductSupplierResource extends Resource
{
    protected static ?string $model = PurchaseProductSupplier::class;

    protected static ?string $navigationIcon = 'heroicon-m-shopping-cart';

    protected static ?string $navigationGroup = 'Supplier Management';

    protected static ?int $navigationSort = 14;

    public static function getModelLabel(): string
    {
        return 'Purchase Product (Supplier)';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Purchase Products (Supplier)';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Purchase Order Information')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('po_number')
                            ->label('PO Number')
                            ->hidden(fn (string $context) => $context === 'create')
                            ->disabled()
                            ->dehydrated()
                            ->columnSpanFull()
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->label('PO Name')
                            ->placeholder('Enter PO Name')
                            ->hint('Example: Monthly Stock Replenishment')
                            ->disabled(fn (Get $get) => $get('status') !== 'Requested')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('type_po')
                            ->options([
                                'credit' => 'Credit Purchase',
                                'cash' => 'Cash Purchase',
                            ])
                            ->label('Type Purchase')
                            ->placeholder('Select Type Purchase')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->disabled(fn (Get $get) => $get('status') !== 'Requested')
                            ->dehydrated()
                            ->required(),
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->label('Requested By')
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->default(auth()->id())
                            ->dehydrated()
                            ->required(),
                        Forms\Components\Select::make('supplier_id')
                            ->options(function (Get $get) {
                                return Supplier::select('id', 'name', 'code')
                                    ->get()
                                    ->mapWithKeys(function ($supplier) {
                                        return [$supplier->id => $supplier->name . ' (' . $supplier->code . ')'];
                                    })
                                    ->toArray();
                            })
                            ->label('Supplier')
                            ->placeholder('Select Supplier')
                            ->disabled(fn (Get $get) => $get('status') !== 'Requested')
                            ->searchable()
                            ->preload()
                            ->dehydrated()
                            ->required(),
                        Forms\Components\DatePicker::make('order_date')
                            ->label('Order Date')
                            ->disabled()
                            ->default(now())
                            ->required()
                            ->live(),
                        Forms\Components\DatePicker::make('received_date')
                            ->label('Received Date')
                            ->minDate(fn (Get $get) => $get('order_date'))
                            ->disabled(fn (Get $get) => $get('status') !== 'Received' && $get('status') !== 'Done')
                            ->hidden(fn (string $context) => $context === 'create')
                            ->live(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'Requested' => 'Requested',
                                'Processing' => 'Processing',
                                'Received' => 'Received',
                                'Done' => 'Done',
                                'Cancelled' => 'Cancelled',
                            ])
                            ->default('Requested')
                            ->columnSpan(function (string $context) {
                                return $context !== 'create' ? 'full' : 1;
                            })
                            ->disabled()
                            ->required(),
                    ]),
                Forms\Components\Section::make('Purchase Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->columns(4)
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->options(function (Get $get) {
                                        $selectedProductIds = collect($get('../../items'))
                                            ->pluck('product_id')
                                            ->filter()
                                            ->toArray();

                                        $currentProductId = $get('product_id');

                                        return Product::select('id', 'name', 'code')
                                            ->when(count($selectedProductIds) > 0, function ($query) use ($selectedProductIds, $currentProductId) {
                                                $excludeIds = array_diff($selectedProductIds, [$currentProductId]);
                                                if (count($excludeIds) > 0) {
                                                    $query->whereNotIn('id', $excludeIds);
                                                }
                                            })
                                            ->get()
                                            ->mapWithKeys(function ($product) {
                                                return [$product->id => $product->name . ' (' . $product->code . ')'];
                                            })
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->disabled(fn (Get $get) => $get('../../status') === 'Done' || $get('../../status') === 'Cancelled')
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        // Optional: bisa set default unit price jika ada
                                    }),
                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0.01)
                                    ->required()
                                    ->live()
                                    ->formatStateUsing(function ($state) {
                                        if (is_null($state)) {
                                            return null;
                                        }
                                        return $state == floor($state) ? (string) intval($state) : $state;
                                    })
                                    ->suffix(function (Get $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) {
                                            return 'pcs';
                                        }

                                        $product = Product::find($productId);
                                        return $product?->unit ?? 'pcs';
                                    })
                                    ->dehydrated()
                                    ->disabled(fn (Get $get) => $get('../../status') === 'Done' || $get('../../status') === 'Cancelled')
                                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                        $unitPrice = $get('unit_price') ?? 0;
                                        $set('total_price', $state * $unitPrice);
                                    }),
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->live()
                                    ->disabled(fn (Get $get) => $get('../../status') === 'Done' || $get('../../status') === 'Cancelled')
                                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                        $quantity = $get('quantity') ?? 0;
                                        $set('total_price', $quantity * $state);
                                    })
                                    ->dehydrated(),
                                Forms\Components\TextInput::make('total_price')
                                    ->label('Total')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->dehydrated(),
                            ])
                            ->live()
                            ->addAction(
                                fn (Forms\Components\Actions\Action $action) => $action
                                    ->label('Add Product')
                                    ->icon('heroicon-m-plus')
                            )
                            ->disabled(fn (Get $get) => $get('status') !== 'Requested')
                            ->deletable(fn (Get $get) => $get('status') === 'Requested')
                            ->reorderable()
                            ->collapsible()
                            ->minItems(1)
                            ->itemLabel(function (array $state): ?string {
                                if (!isset($state['product_id']) || !$state['product_id']) {
                                    return 'New Product';
                                }

                                $product = Product::find($state['product_id']);
                                if (!$product) {
                                    return 'Unknown Product';
                                }

                                return $product->name . ' (' . $product->code . ')';
                            }),
                    ]),
                Forms\Components\Section::make('Order Summary')
                    ->columns(1)
                    ->schema([
                        Forms\Components\Placeholder::make('total_display')
                            ->label('')
                            ->live()
                            ->content(function (Get $get) {
                                $items = $get('items') ?? [];
                                $total = 0;

                                foreach ($items as $item) {
                                    if (isset($item['total_price']) && is_numeric($item['total_price'])) {
                                        $total += $item['total_price'];
                                    }
                                }

                                return 'Total Amount: Rp ' . number_format($total, 0, ',', '.');
                            })
                            ->extraAttributes([
                                'style' => 'font-size: 1.25rem; font-weight: bold; color: #4ECB25; text-align: center;'
                            ]),
                        Forms\Components\Hidden::make('total_amount')
                            ->live()
                            ->default(function (Get $get) {
                                $items = $get('items') ?? [];
                                $total = 0;

                                foreach ($items as $item) {
                                    if (isset($item['total_price']) && is_numeric($item['total_price'])) {
                                        $total += $item['total_price'];
                                    }
                                }

                                return $total;
                            })
                            ->dehydrated(),
                    ]),
                Forms\Components\Section::make('Payment Information')
                    ->columns(1)
                    ->hidden(function (Get $get, string $context) {
                        $typePo = $get('type_po');

                        if (!$typePo) {
                            return true;
                        }

                        if ($typePo === 'credit' && $context === 'create') {
                            return true;
                        }

                        return false;
                    })
                    ->schema([
                        Forms\Components\Select::make('status_paid')
                            ->options([
                                'unpaid' => 'Unpaid',
                                'paid' => 'Paid',
                            ])
                            ->label('Payment Status')
                            ->placeholder('Select Payment Status')
                            ->searchable()
                            ->preload()
                            ->dehydrated()
                            ->disabled(fn (Get $get) => $get('status') === 'Done')
                            ->required(),
                        Forms\Components\FileUpload::make('bukti_tf')
                            ->label('Upload Invoice From Supplier')
                            ->maxSize(3072)
                            ->disk('public')
                            ->columnSpanFull()
                            ->openable()
                            ->downloadable()
                            ->disabled(fn (Get $get) => $get('status') === 'Done')
                            ->directory('po_supplier')
                            ->required()
                            ->image(),
                    ]),
                Textarea::make('notes')
                    ->columnSpanFull()
                    ->rows(3)
                    ->placeholder('Additional notes for this purchase order'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->label('PO Number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('PO Name')
                    ->searchable()
                    ->limit(25),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->formatStateUsing(fn ($record) =>
                        $record->supplier
                            ? $record->supplier->name . ' (' . $record->supplier->code . ')'
                            : '-'
                    )
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Requested By')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type_po')
                    ->label('Type Purchase')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'credit' => 'Credit Purchase',
                        'cash' => 'Cash Purchase',
                        default => ucfirst($state) . ' Purchase'
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'credit' => 'warning',
                        'cash' => 'success'
                    }),
                Tables\Columns\TextColumn::make('order_date')
                    ->label('Order Date')
                    ->date(),
                Tables\Columns\TextColumn::make('status_paid')
                    ->label('Payment Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'unpaid' => 'danger',
                        'paid' => 'success'
                    }),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->formatStateUsing(function ($state) {
                        return 'Rp ' . number_format($state, 0, ',', '.');
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Purchase Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Requested' => 'gray',
                        'Processing' => 'warning',
                        'Received' => 'info',
                        'Done' => 'success',
                        'Cancelled' => 'danger',
                    }),
            ])
            ->filters([
                Tables\Filters\Filter::make('order_date')
                    ->form([
                        Forms\Components\DatePicker::make('order_from')
                            ->label('Order Date From')
                            ->placeholder('Select start date'),
                        Forms\Components\DatePicker::make('order_until')
                            ->label('Order Date Until')
                            ->placeholder('Select end date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['order_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('order_date', '>=', $date),
                            )
                            ->when(
                                $data['order_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('order_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['order_from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Order from ' . \Carbon\Carbon::parse($data['order_from'])->toFormattedDateString())
                                ->removeField('order_from');
                        }

                        if ($data['order_until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Order until ' . \Carbon\Carbon::parse($data['order_until'])->toFormattedDateString())
                                ->removeField('order_until');
                        }

                        return $indicators;
                    }),

                Tables\Filters\SelectFilter::make('supplier')
                    ->relationship('supplier', 'name')
                    ->label('Supplier')
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->placeholder('All Suppliers')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name . ' (' . $record->code . ')'),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Requested' => 'Requested',
                        'Processing' => 'Processing',
                        'Received' => 'Received',
                        'Done' => 'Done',
                        'Cancelled' => 'Cancelled',
                    ])
                    ->multiple()
                    ->placeholder('All Status'),

                Tables\Filters\SelectFilter::make('type_po')
                    ->label('Type Purchase')
                    ->options([
                        'credit' => 'Credit Purchase',
                        'cash' => 'Cash Purchase',
                    ])
                    ->multiple()
                    ->placeholder('All Types'),

                Tables\Filters\SelectFilter::make('status_paid')
                    ->label('Payment Status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'paid' => 'Paid',
                    ])
                    ->multiple()
                    ->placeholder('All Payment Status'),
            ])
            ->actions([
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-c-x-circle')
                    ->color('danger')
                    ->action(function (PurchaseProductSupplier $record) {
                        $record->cancel();

                        Notification::make()
                            ->title('PO Cancelled Successfully')
                            ->body("Purchase order {$record->po_number} has been cancelled.")
                            ->success()
                            ->duration(5000)
                            ->send();
                    })
                    ->visible(fn (PurchaseProductSupplier $record) => $record->status === 'Requested' || $record->status === 'Processing')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Purchase Order')
                    ->modalDescription('Do you want to cancel this purchase order?'),
                Tables\Actions\Action::make('process')
                    ->label('Process')
                    ->icon('heroicon-m-arrows-pointing-in')
                    ->color('warning')
                    ->action(function (PurchaseProductSupplier $record) {
                        try {
                            $record->process();

                            Notification::make()
                                ->title('PO Processed Successfully')
                                ->body("Purchase order {$record->po_number} is now being processed.")
                                ->success()
                                ->duration(5000)
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Processing Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->duration(5000)
                                ->send();
                        }
                    })
                    ->visible(fn (PurchaseProductSupplier $record) => $record->status === 'Requested')
                    ->requiresConfirmation()
                    ->modalHeading('Process Purchase Order')
                    ->modalDescription('Is this purchase order being processed by the supplier?'),

                Tables\Actions\Action::make('receive')
                    ->label('Receive')
                    ->icon('heroicon-m-archive-box')
                    ->color('info')
                    ->action(function (PurchaseProductSupplier $record) {
                        $record->receive();

                        Notification::make()
                            ->title('Purchase Order Received')
                            ->body("Purchase order {$record->po_number} has been successfully received.")
                            ->success()
                            ->duration(5000)
                            ->send();
                    })
                    ->visible(fn (PurchaseProductSupplier $record) => $record->status === 'Processing')
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Purchase Order Receipt')
                    ->modalDescription('Has this purchase order been received from the supplier?'),
                Tables\Actions\Action::make('done')
                    ->label('Done')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->action(function (PurchaseProductSupplier $record) {
                        try {
                            $record->done();

                            Notification::make()
                                ->title('Purchase Order Completed')
                                ->body("Purchase order {$record->po_number} has been marked as Done successfully.")
                                ->success()
                                ->duration(5000)
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Action Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->duration(5000)
                                ->send();
                        }
                    })
                    ->visible(fn (PurchaseProductSupplier $record) => $record->status === 'Received')
                    ->requiresConfirmation()
                    ->modalHeading('Complete Purchase Order')
                    ->modalDescription('Are you sure you want to mark this purchase order as Done?'),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view_faktur')
                        ->label('Faktur')
                        ->icon('heroicon-m-document-text')
                        ->color('success')
                        ->url(fn (PurchaseProductSupplier $record): string => route('purchase-product-supplier.faktur', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (PurchaseProductSupplier $record) => in_array($record->status, ['Requested', 'Processing','Received', 'Done'])),
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (PurchaseProductSupplier $record) =>
                            $record->status !== 'Done' && !($record->type_po === 'cash' && $record->status !== 'Requested' || (($record->status_paid === 'paid' && !empty($record->bukti_tf)) && (Auth::user()->hasRole('Admin') || Auth::user()->hasRole('Supervisor'))))
                        ),
                ])
                    ->link()
                    ->color('gray')
                    ->label('Actions'),
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
            'index' => Pages\ListPurchaseProductSuppliers::route('/'),
            'create' => Pages\CreatePurchaseProductSupplier::route('/create'),
            'view' => Pages\ViewPurchaseProductSupplier::route('/{record}'),
            'edit' => Pages\EditPurchaseProductSupplier::route('/{record}/edit'),
        ];
    }
}
