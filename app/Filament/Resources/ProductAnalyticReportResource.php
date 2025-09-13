<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductAnalyticReportResource\Pages;
use App\Models\ProductAnalyticReport;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;
use Auth;

class ProductAnalyticReportResource extends Resource
{
    protected static ?string $model = ProductAnalyticReport::class;

    protected static ?string $navigationIcon = 'heroicon-m-chart-pie';

    protected static ?string $navigationGroup = 'Product Management';

    protected static ?int $navigationSort = 11;

    public static function getModelLabel(): string
    {
        return 'Product Analytic';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Product Analytics';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $filters = session('product_analytics_filters', []);

                // Join dengan category untuk data lengkap
                $query->select([
                    'products.*',
                    'product_categories.name as category_name',
                ])
                ->selectRaw('(products.stock * products.price) as stock_value') // Add computed stock_value column
                ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id');

                // Add computed columns for purchase activity
                $query->selectRaw('
                    (SELECT COALESCE(SUM(ppi.quantity), 0)
                     FROM purchase_product_items ppi
                     JOIN purchase_products pp ON ppi.purchase_product_id = pp.id
                     WHERE ppi.product_id = products.id
                     AND pp.status IN ("Processing", "Shipped", "Received", "Done")
                    ) as total_purchased
                ');

                $query->selectRaw('
                    (SELECT COALESCE(SUM(ppi.quantity), 0)
                     FROM purchase_product_items ppi
                     JOIN purchase_products pp ON ppi.purchase_product_id = pp.id
                     WHERE ppi.product_id = products.id
                     AND pp.status = "Requested"
                    ) as pending_requests
                ');

                // Apply session filters
                $query = ProductAnalyticReport::applyFiltersToQuery($query, $filters);
            })
            ->defaultSort('total_purchased', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('category_name')
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Product Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->name),

                Tables\Columns\TextColumn::make('stock')
                    ->label('Current Stock')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state) => match(true) {
                        $state <= 0 => 'danger',
                        $state < 10 => 'warning',
                        default => 'success'
                    }),

                Tables\Columns\TextColumn::make('stock_status')
                    ->label('Stock Status')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $displayStock = max(0, $record->stock);
                        if ($displayStock <= 0) {
                            return 'Out of Stock';
                        } elseif ($displayStock < 10) {
                            return 'Low Stock';
                        } else {
                            return 'In Stock';
                        }
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Out of Stock' => 'danger',
                        'Low Stock' => 'warning',
                        'In Stock' => 'success',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Out of Stock' => 'heroicon-m-x-circle',
                        'Low Stock' => 'heroicon-m-exclamation-triangle',
                        'In Stock' => 'heroicon-m-check-circle',
                    }),

                Tables\Columns\TextColumn::make('total_purchased')
                    ->label('Total Purchased')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                                 Tables\Columns\TextColumn::make('need_purchase')
                    ->label('Need Purchase')
                    ->numeric()
                    ->color(fn ($state) => $state > 0 ? 'danger' : null),

                Tables\Columns\TextColumn::make('price')
                    ->label('Selling Price')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock_value')
                    ->label('Stock Value')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->sortable() // Now this will work because we added it as a computed column in the query
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.')),
                    ]),

                Tables\Columns\TextColumn::make('expiry_status')
                    ->label('Expiry Status')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        if (!$record->expiry_date) {
                            return 'No Expiry Date';
                        }

                        $daysUntilExpiry = now()->diffInDays($record->expiry_date, false);

                        if ($daysUntilExpiry < 0) {
                            return 'Expired';
                        } elseif ($daysUntilExpiry <= 30) {
                            return 'Expiring Soon';
                        } else {
                            return 'Fresh';
                        }
                    })
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

                Tables\Columns\TextColumn::make('entry_date')
                    ->label('Entry Date')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('on_request')
                    ->label('Request')
                    ->color('amber')
                    ->numeric(),

                Tables\Columns\TextColumn::make('on_processing')
                    ->label('Processing')
                    ->color('blue')
                    ->numeric(),

                Tables\Columns\TextColumn::make('on_shipped')
                    ->label('Shipped')
                    ->color('purple')
                    ->numeric(),

                Tables\Columns\TextColumn::make('on_received')
                    ->label('Received')
                    ->color('emerald')
                    ->numeric(),

                Tables\Columns\TextColumn::make('on_done')
                    ->label('Done')
                    ->color('success')
                    ->numeric(),

            ])
            ->actions([
                Tables\Actions\Action::make('view_original')
                    ->label('View Product')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->url(fn ($record) => route('filament.admin.resources.products.view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('No Products Found')
            ->emptyStateDescription('There are no products matching your current filters.')
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
            'index' => Pages\ListProductAnalyticReports::route('/'),
        ];
    }
}
