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

                // Join dengan category dan load productBatches
                $query->with(['category', 'productBatches']);

                // Apply session filters
                $query = ProductAnalyticReport::applyFiltersToQuery($query, $filters);
            })
            ->defaultSort('name', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Product Code')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->name),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Current Stock')
                    ->numeric()
                    ->getStateUsing(fn ($record) => $record->total_stock)
                    ->formatStateUsing(fn ($state, $record) => number_format($state) . ' ' . ($record->unit ?? 'pcs'))
                    ->color(fn ($record) => match(true) {
                        $record->total_stock <= 0 => 'danger',
                        $record->total_stock < 10 => 'warning',
                        default => 'success'
                    }),

                Tables\Columns\TextColumn::make('stock_status')
                    ->label('Stock Status')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->stock_status)
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

                Tables\Columns\TextColumn::make('average_cost_price')
                    ->label('Avg Cost Price')
                    ->getStateUsing(fn ($record) => $record->average_cost_price)
                    ->formatStateUsing(fn ($state) => $state > 0 ? 'Rp ' . number_format($state, 0, ',', '.') : '-'),

                Tables\Columns\TextColumn::make('price')
                    ->label('Selling Price')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.')),

                Tables\Columns\TextColumn::make('stock_value')
                    ->label('Stock Value')
                    ->getStateUsing(fn ($record) => $record->stock_value)
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.')),

                Tables\Columns\TextColumn::make('need_purchase')
                    ->label('Need Purchase')
                    ->numeric()
                    ->getStateUsing(fn ($record) => $record->need_purchase)
                    ->color(fn ($record) => $record->need_purchase > 0 ? 'danger' : 'success')
                    ->formatStateUsing(fn ($state, $record) => $state > 0 ? number_format($state) . ' ' . ($record->unit ?? 'pcs') : '-'),

                Tables\Columns\TextColumn::make('batches_count')
                    ->label('Batches')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->productBatches->count() . ' batches')
                    ->color('info'),

                Tables\Columns\TextColumn::make('total_purchased')
                    ->label('Total Purchased')
                    ->numeric()
                    ->getStateUsing(fn ($record) => $record->total_purchased)
                    ->formatStateUsing(fn ($state, $record) => number_format($state) . ' ' . ($record->unit ?? 'pcs'))
                    ->color(fn ($record) => $record->total_purchased > 0 ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('requested')
                    ->label('Requested')
                    ->numeric()
                    ->getStateUsing(fn ($record) => $record->requested)
                    ->formatStateUsing(fn ($state, $record) => number_format($state) . ' ' . ($record->unit ?? 'pcs'))
                    ->color('amber'),

                Tables\Columns\TextColumn::make('processing')
                    ->label('Processing')
                    ->numeric()
                    ->getStateUsing(fn ($record) => $record->processing)
                    ->formatStateUsing(fn ($state, $record) => number_format($state) . ' ' . ($record->unit ?? 'pcs'))
                    ->color('blue'),

                Tables\Columns\TextColumn::make('shipped')
                    ->label('Shipped')
                    ->numeric()
                    ->getStateUsing(fn ($record) => $record->shipped)
                    ->formatStateUsing(fn ($state, $record) => number_format($state) . ' ' . ($record->unit ?? 'pcs'))
                    ->color('purple'),

                Tables\Columns\TextColumn::make('received')
                    ->label('Received')
                    ->numeric()
                    ->getStateUsing(fn ($record) => $record->received)
                    ->formatStateUsing(fn ($state, $record) => number_format($state) . ' ' . ($record->unit ?? 'pcs'))
                    ->color('emerald'),

                Tables\Columns\TextColumn::make('done')
                    ->label('Done')
                    ->numeric()
                    ->getStateUsing(fn ($record) => $record->done)
                    ->formatStateUsing(fn ($state, $record) => number_format($state) . ' ' . ($record->unit ?? 'pcs'))
                    ->color('success'),
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
