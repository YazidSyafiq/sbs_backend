<?php

namespace App\Filament\Resources;

use App\Filament\Resources\POReportSupplierProductResource\Pages;
use App\Models\POReportSupplierProduct;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\ProductCategory;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;

class POReportSupplierProductResource extends Resource
{
    protected static ?string $model = POReportSupplierProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Supplier Management';

    protected static ?int $navigationSort = 12;

    public static function getModelLabel(): string
    {
        return 'Supplier Product Analytics';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Supplier Product Analytics';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $filters = session('supplier_product_filters', []);

                // Join dengan supplier dan product untuk data lengkap
                $query->select([
                    'purchase_product_suppliers.*',
                    'suppliers.name as supplier_name',
                    'suppliers.code as supplier_code',
                    'products.name as product_name',
                    'products.code as product_code',
                    'product_categories.name as category_name',
                ])
                ->leftJoin('suppliers', 'purchase_product_suppliers.supplier_id', '=', 'suppliers.id')
                ->leftJoin('products', 'purchase_product_suppliers.product_id', '=', 'products.id')
                ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id');

                // Use existing scopes
                $query->activeOnly(); // Exclude draft and cancelled

                // Apply session filters
                if (!empty($filters['supplier_id'])) {
                    $query->where('purchase_product_suppliers.supplier_id', $filters['supplier_id']);
                }

                if (!empty($filters['product_id'])) {
                    $query->where('purchase_product_suppliers.product_id', $filters['product_id']);
                }

                if (!empty($filters['category_id'])) {
                    $query->where('product_categories.id', $filters['category_id']);
                }

                if (!empty($filters['type_po'])) {
                    $query->whereIn('purchase_product_suppliers.type_po', $filters['type_po']);
                }

                if (!empty($filters['status'])) {
                    $query->whereIn('purchase_product_suppliers.status', $filters['status']);
                }

                if (!empty($filters['status_paid'])) {
                    $query->whereIn('purchase_product_suppliers.status_paid', $filters['status_paid']);
                }

                if (!empty($filters['date_from'])) {
                    $query->whereDate('purchase_product_suppliers.order_date', '>=', $filters['date_from']);
                }

                if (!empty($filters['date_until'])) {
                    $query->whereDate('purchase_product_suppliers.order_date', '<=', $filters['date_until']);
                }

                if (!empty($filters['outstanding_only'])) {
                    $query->where('purchase_product_suppliers.status_paid', 'unpaid');
                }
            })
            ->defaultSort('order_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->label('PO Number')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier_name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->supplier_code ? "Code: {$record->supplier_code}" : null),

                Tables\Columns\TextColumn::make('product_name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->product_code ? "Code: {$record->product_code}" : null),

                Tables\Columns\TextColumn::make('category_name')
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->formatStateUsing(fn ($state) => number_format($state)),
                    ]),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Value')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.')),
                    ]),

                Tables\Columns\TextColumn::make('type_po')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'credit' => 'Credit',
                        'cash' => 'Cash',
                        default => ucfirst($state)
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'credit' => 'warning',
                        'cash' => 'success'
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Requested' => 'amber',
                        'Processing' => 'blue',
                        'Shipped' => 'purple',
                        'Received' => 'emerald',
                        'Done' => 'success',
                        default => 'slate'
                    }),

                Tables\Columns\TextColumn::make('status_paid')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : 'Pending')
                    ->color(fn (?string $state): string => match ($state) {
                        'unpaid' => 'danger',
                        'paid' => 'success',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('outstanding_debt')
                    ->label('Outstanding')
                    ->state(function ($record) {
                        return $record->status_paid === 'unpaid' ? $record->total_amount : 0;
                    })
                    ->formatStateUsing(fn ($state) => $state > 0 ? 'Rp ' . number_format($state, 0, ',', '.') : '-')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('order_date')
                    ->label('Order Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('received_date')
                    ->label('Goods Received Date')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Requested By')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Optional: tambahkan filter langsung di table jika diperlukan
            ])
            ->actions([
                Tables\Actions\Action::make('view_original')
                    ->label('View Original PO')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->url(fn ($record) => route('filament.admin.resources.purchase-product-suppliers.view', ['record' => $record]))
                    ->openUrlInNewTab()
                    ->visible(function () {
                        // Only show if the original resource exists
                        try {
                            return class_exists('App\Filament\Resources\PurchaseProductSupplierResource');
                        } catch (Exception $e) {
                            return false;
                        }
                    }),
            ])
            ->emptyStateHeading('No Purchase Orders Found')
            ->emptyStateDescription('There are no purchase orders matching your current filters.')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('analytics_notice')
                ->label('')
                ->content('This is a read-only analytics report. No editing is allowed.')
                ->columnSpanFull(),
        ]);
    }

    // Disable CRUD operations
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPOReportSupplierProducts::route('/'),
        ];
    }
}
