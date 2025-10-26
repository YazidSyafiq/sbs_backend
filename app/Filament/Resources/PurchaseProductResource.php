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

    public static function getNavigationGroup(): ?string
    {
        $user = Auth::user();

        if ($user && $user->hasRole('User')) {
            return 'Purchase Product Management';
        }

        return 'Product Sales Management';
    }

    protected static ?int $navigationSort = 16;

    public static function getModelLabel(): string
    {
        $user = Auth::user();

        if ($user && $user->hasRole('User')) {
            return 'Purchase Product';
        }

        return 'Product Sales';
    }

    public static function getPluralModelLabel(): string
    {
        $user = Auth::user();

        if ($user && $user->hasRole('User')) {
            return 'Purchase Products';
        }

        return 'Product Sales';
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
                            ->columnSpanFull()
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->label('PO Name')
                            ->placeholder('Enter PO Name')
                            ->hint('Example: Monthly Stock Replenishment')
                            ->disabled(fn (Get $get) => $get('status') !== 'Draft')
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
                            ->disabled(fn (Get $get) => $get('status') !== 'Draft')
                            ->dehydrated()
                            ->required(),
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
                            ->disabled(fn (Get $get) => $get('status') === 'Shipped' || $get('status') === 'Received' || $get('status') === 'Done' || $get('status') === 'Cancelled' || Auth::user()->hasRole('User'))
                            ->hidden(fn (string $context) => $context === 'create' && Auth::user()->hasRole('User'))
                            ->live(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'Draft' => 'Draft',
                                'Requested' => 'Requested',
                                'Processing' => 'Processing',
                                'Shipped' => 'Shipped',
                                'Received' => 'Received',
                                'Done' => 'Done',
                                'Cancelled' => 'Cancelled',
                            ])
                            ->default('Draft')
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
                                    ->disabled(fn (Get $get) => $get('../../status') !== 'Draft')
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            $set('unit_price', $product->price ?? 0);
                                        }
                                    }),
                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->step(0.01) // Memperbolehkan desimal
                                    ->minValue(0.01)
                                    ->required()
                                    ->live()
                                    ->formatStateUsing(function ($state) {
                                        if (is_null($state)) {
                                            return null;
                                        }
                                        // Hilangkan desimal jika nilainya bulat
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
                                    ->disabled(fn (Get $get) => $get('../../status') !== 'Draft')
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
                            ->disabled(fn (Get $get) => $get('status') !== 'Draft')
                            ->deletable(fn (Get $get) => $get('status') === 'Draft')
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
                Forms\Components\Section::make('Payment Information')
                    ->columns(1)
                    ->hidden(function (Get $get, string $context) {
                        $typePo = $get('type_po');

                        // Hidden jika type_po belum dipilih
                        if (!$typePo) {
                            return true;
                        }

                        // Hidden jika credit DAN context adalah create
                        if ($typePo === 'credit' && $context === 'create') {
                            return true;
                        }

                        // Tampilkan untuk kondisi lainnya:
                        // - Cash (create & edit)
                        // - Credit (hanya edit)
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
                            ->disabled(fn (Get $get) => $get('status') === 'Done' || !Auth::user()->hasRole('User'))
                            ->required(),
                        Forms\Components\FileUpload::make('bukti_tf')
                            ->label('Upload Payment Receipt')
                            ->maxSize(3072)
                            ->openable() // Tambahkan ini untuk full screen view
                            ->downloadable() // Optional: untuk bisa download
                            ->disk('public')
                            ->columnSpanFull()
                            ->disabled(fn (Get $get) => $get('status') === 'Done' || !Auth::user()->hasRole('User'))
                            ->directory('po_product')
                            ->required(function (string $context, ?PurchaseProduct $record = null) {
                                // Saat create, tidak required
                                if ($context === 'create') {
                                    return true;
                                }

                                // Jika record tidak ada, tidak required
                                if (!$record) {
                                    return true;
                                }

                                $user = Auth::user();

                                // Jika status Draft, semua bisa cancel
                                if ($record->status === 'Draft' && $record->type_po === 'cash') {
                                    return true;
                                }

                                // Jika status Requested
                                if ($record->status !== 'Draft') {
                                    // Hanya credit PO yang bisa di-cancel saat status Requested
                                    if ($record->type_po === 'credit' && $record->status === 'Done') {
                                        return true;
                                    }

                                    // Cash PO tidak bisa di-cancel setelah requested
                                    return false;
                                }

                                // Status lain tidak bisa cancel
                                return false;
                            })
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
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Draft' => 'slate',       // Abu-abu gelap untuk draft
                        'Requested' => 'amber',   // Kuning untuk menunggu approval
                        'Processing' => 'blue',   // Biru untuk sedang diproses
                        'Shipped' => 'purple',    // Ungu untuk sudah dikirim
                        'Received' => 'emerald',    // Hijau untuk sudah diterima
                        'Done' => 'success',      // Hijau success untuk selesai
                        'Cancelled' => 'red',     // Merah untuk dibatalkan
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-c-x-circle')
                    ->color('red')
                    ->action(function (PurchaseProduct $record) {
                        $record->cancel();

                        Notification::make()
                            ->title('PO Cancelled Successfully')
                            ->body("Purchase order {$record->po_number} has been cancelled.")
                            ->success()
                            ->duration(5000)
                            ->send();
                    })
                    ->visible(function (PurchaseProduct $record) {
                        // Hanya bisa cancel saat Draft atau Requested
                        if (in_array($record->status, ['Draft', 'Requested'])) {
                            return true;
                        }
                        return false;
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Purchase Order')
                    ->modalDescription(function (PurchaseProduct $record) {
                        if ($record->type_po === 'cash' && $record->status === 'Requested') {
                            return 'Note: Cash purchase orders cannot be cancelled after submission due to payment processing.';
                        }
                        return 'Do you want to cancel this purchase order?';
                    }),
                Tables\Actions\Action::make('request')
                    ->label('Request')
                    ->icon('heroicon-m-paper-airplane')
                    ->color('amber')
                    ->action(function (PurchaseProduct $record) {
                        $requestCheck = $record->canBeRequested();

                        if (!$requestCheck['can_request']) {
                            $title = "Cannot Submit Request";
                            $message = "";

                            foreach ($requestCheck['validation_errors'] as $error) {
                                $message .= "⚠️ {$error}<br><br>";
                            }

                            Notification::make()
                                ->title($title)
                                ->body($message)
                                ->danger()
                                ->persistent()
                                ->duration(8000)
                                ->send();

                            return;
                        }

                        $record->request();

                        Notification::make()
                            ->title('PO Requested Successfully')
                            ->body("Purchase order {$record->po_number} has been submitted for processing.")
                            ->success()
                            ->duration(5000)
                            ->send();
                    })
                    ->visible(fn (PurchaseProduct $record) => $record->status === 'Draft' && Auth::user()->hasRole('User'))
                    ->requiresConfirmation()
                    ->modalHeading('Submit Purchase Request')
                    ->modalDescription(function (PurchaseProduct $record) {
                        if ($record->type_po === 'cash') {
                            return 'Submit this cash purchase request? Make sure payment has been completed and receipt uploaded.';
                        }
                        return 'Submit this credit purchase request for processing?';
                    }),
                Tables\Actions\Action::make('process')
                    ->label('Process')
                    ->icon('heroicon-m-arrows-pointing-in')
                    ->color('blue')
                    ->action(function (PurchaseProduct $record) {
                        $processCheck = $record->canBeProcessed();

                        if (!$processCheck['can_process']) {
                            $title = "Cannot Process {$record->po_number}";
                            $message = "";

                            if (!empty($processCheck['validation_errors'])) {
                                foreach ($processCheck['validation_errors'] as $error) {
                                    $message .= "⚠️ {$error}<br><br>";
                                }
                            }

                            if (!empty($processCheck['insufficient_items'])) {
                                $insufficientCount = count($processCheck['insufficient_items']);
                                $message .= "{$insufficientCount} item(s) have insufficient stock:<br><br>";

                                foreach ($processCheck['insufficient_items'] as $item) {
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

                        $record->process();

                        Notification::make()
                            ->title('PO Processed Successfully')
                            ->body("Purchase order {$record->po_number} has been processed. Stock has been reserved and cost analysis completed.")
                            ->success()
                            ->duration(5000)
                            ->send();
                    })
                    ->visible(fn (PurchaseProduct $record) => $record->status === 'Requested' && !Auth::user()->hasRole('User'))
                    ->requiresConfirmation()
                    ->modalHeading('Process Purchase Order')
                    ->modalDescription('Are you sure you want to process this purchase order? This will reserve stock and calculate cost analysis.'),
                Tables\Actions\Action::make('ship')
                    ->label('Ship')
                    ->icon('heroicon-m-truck')
                    ->color('purple')
                    ->action(function (PurchaseProduct $record) {
                        $shipCheck = $record->canBeShipped();

                        if (!$shipCheck['can_ship']) {
                            $title = "Cannot Ship {$record->po_number}";
                            $message = "";

                            foreach ($shipCheck['validation_errors'] as $error) {
                                $message .= "⚠️ {$error}<br><br>";
                            }

                            Notification::make()
                                ->title($title)
                                ->body($message)
                                ->danger()
                                ->persistent()
                                ->duration(8000)
                                ->send();

                            return;
                        }

                        $record->ship();

                        Notification::make()
                            ->title('PO Shipped Successfully')
                            ->body("Purchase order {$record->po_number} has been shipped.")
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
                    ->color('emerald')
                    ->action(function (PurchaseProduct $record) {
                        $record->received();

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
                Tables\Actions\Action::make('complete')
                    ->label('Done')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->action(function (PurchaseProduct $record) {
                        $completeCheck = $record->canBeCompleted();

                        if (!$completeCheck['can_complete']) {
                            $title = "Cannot Complete PO";
                            $message = "";

                            foreach ($completeCheck['validation_errors'] as $error) {
                                $message .= "⚠️ {$error}<br><br>";
                            }

                            Notification::make()
                                ->title($title)
                                ->body($message)
                                ->danger()
                                ->persistent()
                                ->duration(8000)
                                ->send();

                            return;
                        }

                        $record->done();

                        Notification::make()
                            ->title('PO Completed Successfully')
                            ->body("Purchase order {$record->po_number} has been completed.")
                            ->success()
                            ->duration(5000)
                            ->send();
                    })
                    ->visible(fn (PurchaseProduct $record) => $record->status === 'Received')
                    ->requiresConfirmation()
                    ->modalHeading('Complete Purchase Order')
                    ->modalDescription('Mark this purchase order as done? This action cannot be undone.'),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view_invoice')
                        ->label('Invoice')
                        ->icon('heroicon-m-document-text')
                        ->color('info')
                        ->url(fn (PurchaseProduct $record): string => route('purchase-product.invoice', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (PurchaseProduct $record) => in_array($record->status, ['Requested', 'Processing', 'Shipped', 'Received', 'Done']) && !Auth::user()->hasRole('User')),
                    Tables\Actions\Action::make('view_faktur')
                        ->label('Faktur')
                        ->icon('heroicon-m-document-text')
                        ->color('success')
                        ->url(fn (PurchaseProduct $record): string => route('purchase-product.faktur', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (PurchaseProduct $record) => in_array($record->status, ['Shipped', 'Received', 'Done']) && !Auth::user()->hasRole('User')),
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(function (PurchaseProduct $record) {
                            $user = Auth::user();

                            // Jika bukan User role, bisa edit selain status Done dan Cancelled
                            if (!$user->hasRole('User')) {
                                if(in_array($record->status, ['Done', 'Cancelled'])) {
                                    return false;
                                }
                                return true;
                            }

                            // Jika User role
                            if ($user->hasRole('User')) {
                                if(in_array($record->status, ['Done', 'Cancelled'])) {
                                    return false;
                                }

                                // Cash PO: hanya bisa edit saat Draft
                                if ($record->type_po === 'cash') {
                                    return $record->status === 'Draft';
                                }

                                // Credit PO: bisa edit kecuali saat Done/Cancelled
                                if ($record->type_po === 'credit') {
                                    return !in_array($record->status, ['Done', 'Cancelled']);
                                }
                            }

                            return false;
                        }),
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
