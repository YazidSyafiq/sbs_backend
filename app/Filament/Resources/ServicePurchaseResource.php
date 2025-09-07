<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServicePurchaseResource\Pages;
use App\Filament\Resources\ServicePurchaseResource\RelationManagers;
use App\Models\ServicePurchase;
use App\Models\Service;
use App\Models\Technician;
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

class ServicePurchaseResource extends Resource
{
    protected static ?string $model = ServicePurchase::class;

    protected static ?string $navigationIcon = 'heroicon-m-shopping-cart';

    protected static ?string $navigationGroup = 'Purchase Service Management';

    protected static ?int $navigationSort = 15;

    public static function getModelLabel(): string
    {
        return 'Purchase Service';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Purchase Services';
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
                                return ServicePurchase::generatePoNumber($userId, $orderDate);
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
                        Forms\Components\DatePicker::make('expected_proccess_date')
                            ->label('Expected Proccess Date')
                            ->minDate(fn (Get $get) => $get('order_date'))
                            ->disabled(fn (Get $get) => $get('status') === 'In Progress' || $get('status') === 'Done' || $get('status') === 'Cancelled' || Auth::user()->hasRole('User'))
                            ->hidden(fn (string $context) => $context === 'create' && Auth::user()->hasRole('User'))
                            ->live(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'Draft' => 'Draft',
                                'Requested' => 'Requested',
                                'Approved' => 'Approved',
                                'In Progress' => 'In Progress',
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
                            ->columns(2)
                            ->schema([
                                Forms\Components\Select::make('service_id')
                                    ->label('Product')
                                    ->options(function (Get $get) {
                                        // Ambil semua service IDs yang sudah dipilih di items lain
                                        $selectedServiceIds = collect($get('../../items'))
                                            ->pluck('service_id')
                                            ->filter()
                                            ->toArray();

                                        // Ambil current service ID (untuk edit mode)
                                        $currentServiceId = $get('service_id');

                                        // Filter out selected products kecuali current item
                                        return Service::select('id', 'name', 'code')
                                            ->when(count($selectedServiceIds) > 0, function ($query) use ($selectedServiceIds, $currentServiceId) {
                                                $excludeIds = array_diff($selectedServiceIds, [$currentServiceId]);
                                                if (count($excludeIds) > 0) {
                                                    $query->whereNotIn('id', $excludeIds);
                                                }
                                            })
                                            ->get()
                                            ->mapWithKeys(function ($service) {
                                                return [$service->id => $service->name . ' (' . $service->code . ')'];
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
                                            $service = Service::find($state);
                                            $set('selling_price', $service->price ?? 0);
                                        }
                                    }),
                                Forms\Components\TextInput::make('selling_price')
                                    ->label('Price')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->disabled()
                                    ->dehydrated(),
                                Forms\Components\Select::make('technician_id')
                                    ->label('Technician')
                                    ->options(function (Get $get) {
                                        // Filter out selected products kecuali current item
                                        return Technician::select('id', 'name', 'code')
                                            ->get()
                                            ->mapWithKeys(function ($technician) {
                                                return [$technician->id => $technician->name . ' (' . $technician->code . ')'];
                                            })
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->placeholder('Select Technician')
                                    ->columnSpanFull()
                                    ->hidden(fn (Get $get) => $get('../../status') === 'Draft')
                                    ->disabled(fn (Get $get) => $get('../../status') === 'Approved' || $get('../../status') === 'In Progress' ||  $get('../../status') === 'Done' ||  $get('../../status') === 'Cancelled' || Auth::user()->hasRole('User'))
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $service = Technician::find($state);
                                            $set('cost_price', $service->price ?? 0);
                                        }
                                    }),
                                Forms\Components\Hidden::make('cost_price')
                                    ->label('Cost Price')
                                    ->dehydrated(),
                            ])
                            ->live()
                            ->addAction(
                                fn (Forms\Components\Actions\Action $action) => $action
                                    ->label('Add Product')
                                    ->icon('heroicon-m-plus')
                            )
                            ->deletable(fn (Get $get) => $get('status') === 'Draft')
                            ->reorderable()
                            ->collapsible()
                            ->minItems(1)
                            ->itemLabel(function (array $state): ?string {
                                if (!isset($state['service_id']) || !$state['service_id']) {
                                    return 'New Product';
                                }

                                $service = Service::find($state['service_id']);
                                if (!$service) {
                                    return 'Unknown Product';
                                }

                                return $service->name . ' (' . $service->code . ')';
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
                                    if (isset($item['selling_price']) && is_numeric($item['selling_price'])) {
                                        $total += $item['selling_price'];
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
                                    if (isset($item['selling_price']) && is_numeric($item['selling_price'])) {
                                        $total += $item['selling_price'];
                                    }
                                }

                                return $total;
                            })
                            ->dehydrated(),
                    ]),
                Forms\Components\Section::make('Payment Information')
                    ->columns(1)
                    ->hidden(fn (string $context) => $context === 'create')
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
                            ->label('Upload Payment Receipt')
                            ->maxSize(3072)
                            ->disk('public')
                            ->columnSpanFull()
                            ->disabled(fn (Get $get) => $get('status') === 'Done')
                            ->directory('po_product')
                            ->required(function (ServicePurchase $record) {
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
                        'Approved' => 'blue',   // Biru untuk sedang diproses
                        'In Progress' => 'purple',    // Ungu untuk sudah dikirim
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
                    ->action(function (ServicePurchase $record) {
                        $record->cancel();

                        Notification::make()
                            ->title('PO Cancelled Successfully')
                            ->body("Purchase order {$record->po_number} has been cancelled.")
                            ->success()
                            ->duration(5000)
                            ->send();
                    })
                    ->visible(function (ServicePurchase $record) {
                        // Jika status Draft, semua bisa cancel
                        if ($record->status === 'Draft' || $record->status === 'Requested' ) {
                            return true;
                        }
                        // Status lain tidak bisa cancel
                        return false;
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Purchase Order')
                    ->modalDescription(function (ServicePurchase $record) {
                        if ($record->type_po === 'cash' && $record->status === 'Requested') {
                            return 'Note: Cash purchase orders cannot be cancelled after submission due to payment processing.';
                        }
                        return 'Do you want to cancel this purchase order?';
                    }),
                Tables\Actions\Action::make('request')
                    ->label('Request')
                    ->icon('heroicon-m-paper-airplane')
                    ->color('amber')
                    ->action(function (ServicePurchase $record) {
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
                    ->visible(fn (ServicePurchase $record) => $record->status === 'Draft' && Auth::user()->hasRole('User'))
                    ->requiresConfirmation()
                    ->modalHeading('Submit Purchase Request')
                    ->modalDescription(function (ServicePurchase $record) {
                        if ($record->type_po === 'cash') {
                            return 'Submit this cash purchase request? Make sure payment has been completed and receipt uploaded.';
                        }
                        return 'Submit this credit purchase request for processing?';
                    }),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-c-cursor-arrow-ripple')
                    ->color('blue')
                    ->action(function (ServicePurchase $record) {
                        $approveCheck = $record->canBeApproved();

                        if (!$approveCheck['can_approve']) {
                            $title = "Cannot Approve Request";
                            $message = "";

                            foreach ($approveCheck['validation_errors'] as $error) {
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

                        $record->approve();

                        Notification::make()
                            ->title('PO Approved Successfully')
                            ->body("Purchase order {$record->po_number} has been approved.")
                            ->success()
                            ->duration(5000)
                            ->send();
                    })
                    ->visible(fn (ServicePurchase $record) => $record->status === 'Requested' && Auth::user()->hasAnyRole(['Admin', 'Supervisor', 'Manager', 'Super Admin']))
                    ->requiresConfirmation()
                    ->modalHeading('Approve Purchase Request')
                    ->modalDescription('Are you sure you want to approve this purchase request? Make sure all technicians have been assigned.'),
                Tables\Actions\Action::make('in_progress')
                    ->label('Set In Progress')
                    ->icon('heroicon-c-bolt')
                    ->color('purple')
                    ->action(function (ServicePurchase $record) {
                        $approveCheck = $record->canBeProgress();

                        if (!$approveCheck['can_proccess']) {
                            $title = "Cannot Set In Progress";
                            $message = "";

                            foreach ($approveCheck['validation_errors'] as $error) {
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

                        $record->progress();

                        Notification::make()
                            ->title('PO Status Updated')
                            ->body("Purchase order {$record->po_number} is now in progress.")
                            ->success()
                            ->duration(5000)
                            ->send();
                    })
                    ->visible(fn (ServicePurchase $record) => $record->status === 'Approved' && Auth::user()->hasAnyRole(['Admin', 'Supervisor', 'Manager', 'Super Admin']))
                    ->requiresConfirmation()
                    ->modalHeading('Set Purchase Order In Progress')
                    ->modalDescription('Mark this purchase order as in progress? Make sure the expected process date has been set.'),
                Tables\Actions\Action::make('complete')
                    ->label('Done')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->action(function (ServicePurchase $record) {
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
                    ->visible(fn (ServicePurchase $record) => $record->status === 'In Progress')
                    ->requiresConfirmation()
                    ->modalHeading('Complete Purchase Order')
                    ->modalDescription('Mark this purchase order as done? This action cannot be undone.'),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view_invoice')
                        ->label('Invoice')
                        ->icon('heroicon-m-document-text')
                        ->color('info')
                        ->url(fn (ServicePurchase $record): string => route('purchase-service.invoice', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (ServicePurchase $record) => in_array($record->status, ['Approved', 'In Progress', 'Done']) && !Auth::user()->hasRole('User')),
                    Tables\Actions\Action::make('view_faktur')
                        ->label('Faktur')
                        ->icon('heroicon-m-document-text')
                        ->color('success')
                        ->url(fn (ServicePurchase $record): string => route('purchase-service.faktur', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (ServicePurchase $record) => in_array($record->status, ['Approved', 'In Progress', 'Done']) && !Auth::user()->hasRole('User')),
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(function (ServicePurchase $record) {
                            $user = Auth::user();

                            // Jika bukan User role, bisa edit selain status Done
                            if (!$user->hasRole('User')) {

                                if($record->status === 'Done' || $record->status === 'Cancelled') {
                                    return false;
                                }

                                return $record->status !== 'Done';
                            }

                            // Jika User role
                            if ($user->hasRole('User')) {

                                if($record->status === 'Done' || $record->status === 'Cancelled') {
                                    return false;
                                }

                                // Cash PO: hanya bisa edit saat Draft
                                if ($record->type_po === 'cash') {
                                    return $record->status === 'Draft';
                                }

                                // Credit PO: bisa edit kecuali saat Done
                                if ($record->type_po === 'credit') {
                                    return $record->status !== 'Done';
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
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListServicePurchases::route('/'),
            'create' => Pages\CreateServicePurchase::route('/create'),
            'view' => Pages\ViewServicePurchase::route('/{record}'),
            'edit' => Pages\EditServicePurchase::route('/{record}/edit'),
        ];
    }
}
