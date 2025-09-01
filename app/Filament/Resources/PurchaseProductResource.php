<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseProductResource\Pages;
use App\Filament\Resources\PurchaseProductResource\RelationManagers;
use App\Models\PurchaseProduct;
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

class PurchaseProductResource extends Resource
{
    protected static ?string $model = PurchaseProduct::class;

    protected static ?string $navigationIcon = 'heroicon-m-shopping-cart';

    protected static ?string $navigationGroup = 'Purchase Management';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return 'Purchase Product';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Purchase Products';
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
                            ->default(function (Get $get) {
                                $userId = $get('user_id') ?? auth()->id();
                                $orderDate = $get('order_date') ?? now()->format('Y-m-d');
                                return PurchaseProduct::generatePoNumber($userId, $orderDate);
                            })
                            ->disabled()
                            ->dehydrated()
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->label('PO Name')
                            ->placeholder('Enter PO Name')
                            ->hint('Example: Monthly Stock Replenishment')
                            ->disabled(fn (Get $get) => $get('status') !== 'Requested')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->label('Requested By')
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->columnSpanFull()
                            ->default(auth()->id())
                            ->dehydrated()
                            ->required(),
                        Forms\Components\DatePicker::make('order_date')
                            ->label('Order Date')
                            ->disabled()
                            ->hidden(fn (string $context) => $context === 'create' && Auth::user()->hasRole('User'))
                            ->default(now())
                            ->required()
                            ->live(),
                        Forms\Components\DatePicker::make('expected_delivery_date')
                            ->label('Expected Delivery Date')
                            ->minDate(fn (Get $get) => $get('order_date'))
                            ->disabled(fn (Get $get) => $get('status') === 'Shipped' || $get('status') === 'Received' || $get('status') === 'Cancelled' || Auth::user()->hasRole('User'))
                            ->hidden(fn (string $context) => $context === 'create' && Auth::user()->hasRole('User'))
                            ->live(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'Requested' => 'Requested',
                                'Processing' => 'Processing',
                                'Shipped' => 'Shipped',
                                'Received' => 'Received',
                                'Cancelled' => 'Cancelled',
                            ])
                            ->default('Requested')
                            ->columnSpanFull()
                            ->disabled()
                            ->hidden(fn (string $context) => $context === 'create' && Auth::user()->hasRole('User'))
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
                                        // Ambil semua product IDs yang sudah dipilih di items lain
                                        $selectedProductIds = collect($get('../../items'))
                                            ->pluck('product_id')
                                            ->filter()
                                            ->toArray();

                                        // Ambil current product ID (untuk edit mode)
                                        $currentProductId = $get('product_id');

                                        // Filter out selected products kecuali current item
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
                                    ->disabled(fn (Get $get) => $get('../../status') === 'Processing')
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            $set('unit_price', $product->price ?? 0);
                                        }
                                    }),
                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required()
                                    ->live()
                                    ->dehydrated()
                                    ->disabled(fn (Get $get) => $get('../../status') === 'Processing')
                                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                        $unitPrice = $get('unit_price') ?? 0;
                                        $set('total_price', $state * $unitPrice);
                                    }),
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->disabled()
                                    ->live()
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
                            ->disabled(fn (Get $get) => $get('status') === 'Shipped')
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
                            ->live() // Tambahkan live di Placeholder
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
                            ->live() // Tambahkan live
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
                Textarea::make('notes')
                    ->columnSpanFull()
                    ->rows(3)
                    ->placeholder('Additional notes for this purchase order'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = Auth::user();

                if ($user->hasRole('User')) {
                    // Filter PO berdasarkan user yang memiliki branch_id yang sama
                    $query->whereHas('user', function ($userQuery) use ($user) {
                        $userQuery->where('branch_id', $user->branch_id);
                    });
                }
            })
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->label('PO Number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('PO Name')
                    ->searchable()
                    ->limit(25),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Requested By')
                    ->searchable(),
                Tables\Columns\TextColumn::make('order_date')
                    ->label('Order Date')
                    ->date(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Requested' => 'gray',
                        'Processing' => 'warning',
                        'Shipped' => 'info',
                        'Received' => 'success',
                        'Cancelled' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->formatStateUsing(function ($state) {
                        return 'Rp ' . number_format($state, 0, ',', '.');
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-c-x-circle')
                    ->color('danger')
                    ->action(function (PurchaseProduct $record) {
                        $record->cancel();

                        Notification::make()
                            ->title('PO Cancelled Successfully')
                            ->body("Purchase order {$record->po_number} has been cancelled.")
                            ->success()
                            ->duration(5000)
                            ->send();
                    })
                    ->visible(fn (PurchaseProduct $record) => $record->status === 'Requested')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Purchase Order')
                    ->modalDescription('Do you want to cancel this purchase order?'),
                Tables\Actions\Action::make('process')
                    ->label('Process')
                    ->icon('heroicon-m-arrows-pointing-in')
                    ->color('primary')
                    ->action(function (PurchaseProduct $record) {
                        $record->process();

                        Notification::make()
                            ->title('PO Processed Successfully')
                            ->body("Purchase order {$record->po_number} has been approved and is now being processed.")
                            ->success()
                            ->duration(5000) // Auto close after 1 second
                            ->send();
                    })
                    ->visible(fn (PurchaseProduct $record) => $record->status === 'Requested' && !Auth::user()->hasRole('User'))
                    ->requiresConfirmation()
                    ->modalHeading('Process Purchase Order')
                    ->modalDescription('Do you want to process this purchase order and prepare all items for delivery?'),
                Tables\Actions\Action::make('ship')
                    ->label('Ship')
                    ->icon('heroicon-m-truck')
                    ->color('info')
                    ->action(function (PurchaseProduct $record) {
                        $shipCheck = $record->canBeShipped();

                        if (!$shipCheck['can_ship']) {
                            $title = "Cannot Ship {$record->po_number}";
                            $message = "";

                            // Tampilkan validation errors dulu
                            if (!empty($shipCheck['validation_errors'])) {
                                foreach ($shipCheck['validation_errors'] as $error) {
                                    $message .= "⚠️ {$error}<br><br>";
                                }
                            }

                            // Kemudian tampilkan insufficient stock
                            if (!empty($shipCheck['insufficient_items'])) {
                                $insufficientCount = count($shipCheck['insufficient_items']);
                                $message .= "{$insufficientCount} item(s) have insufficient stock:<br><br>";

                                foreach ($shipCheck['insufficient_items'] as $item) {
                                    $message .= "• {$item['product_name']} ({$item['product_code']}) - Need: <strong style='color:red;'>{$item['shortage']}</strong><br>";
                                }
                            }

                            Notification::make()
                                ->title($title)
                                ->body($message)
                                ->danger()
                                ->persistent()
                                ->duration(10000)
                                ->send();

                            return;
                        }

                        $record->ship();

                        Notification::make()
                            ->title('PO Shipped Successfully')
                            ->body("Purchase order {$record->po_number} has been shipped and stock has been deducted.")
                            ->success()
                            ->duration(5000)
                            ->send();
                    })
                    ->visible(fn (PurchaseProduct $record) => $record->status === 'Processing' && !Auth::user()->hasRole('User'))
                    ->requiresConfirmation()
                    ->modalHeading('Ship Purchase Order')
                    ->modalDescription('Are you sure you want to ship this purchase order?'),
                Tables\Actions\Action::make('receive')
                    ->label(function () {
                        return Auth::user()->hasRole('User') ? 'Mark Received' : 'Confirm Receipt';
                    })
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->action(function (PurchaseProduct $record) {
                        $record->update(['status' => 'Received']);

                        Notification::make()
                            ->title('PO Received Successfully')
                            ->body("Purchase order {$record->po_number} has been marked as received.")
                            ->success()
                            ->duration(5000)
                            ->send();
                    })
                    ->visible(fn (PurchaseProduct $record) => $record->status === 'Shipped')
                    ->requiresConfirmation()
                    ->modalHeading(function () {
                        return Auth::user()->hasRole('User') ? 'Mark as Received' : 'Confirm Receipt';
                    })
                    ->modalDescription(function () {
                        return Auth::user()->hasRole('User')
                            ? 'Confirm that you have received all items from this purchase order?'
                            : 'Confirm that all items in this purchase order have been received?';
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (PurchaseProduct $record) => $record->status === 'Requested' || !Auth::user()->hasRole('User')),
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
            'index' => Pages\ListPurchaseProducts::route('/'),
            'create' => Pages\CreatePurchaseProduct::route('/create'),
            'view' => Pages\ViewPurchaseProduct::route('/{record}'),
            'edit' => Pages\EditPurchaseProduct::route('/{record}/edit'),
        ];
    }
}
