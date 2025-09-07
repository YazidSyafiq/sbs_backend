<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceAnalyticReportResource\Pages;
use App\Models\ServiceAnalyticReport;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;

class ServiceAnalyticReportResource extends Resource
{
    protected static ?string $model = ServiceAnalyticReport::class;

    protected static ?string $navigationIcon = 'heroicon-m-chart-pie';

    protected static ?string $navigationGroup = 'Service Management';

    protected static ?int $navigationSort = 9;

    public static function getModelLabel(): string
    {
        return 'Service Analytic';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Service Analytics';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $filters = session('service_analytics_filters', []);

                // Join dengan category untuk data lengkap
                $query->select([
                    'services.*',
                    'product_categories.name as category_name',
                ])
                ->leftJoin('product_categories', 'services.category_id', '=', 'product_categories.id');

                // Add computed columns for each PO status
                $query->selectRaw('
                    (SELECT COALESCE(COUNT(spi.id), 0)
                     FROM service_purchase_items spi
                     JOIN service_purchases sp ON spi.service_purchase_id = sp.id
                     WHERE spi.service_id = services.id
                     AND sp.status = "Requested"
                    ) as on_requested
                ');

                $query->selectRaw('
                    (SELECT COALESCE(COUNT(spi.id), 0)
                     FROM service_purchase_items spi
                     JOIN service_purchases sp ON spi.service_purchase_id = sp.id
                     WHERE spi.service_id = services.id
                     AND sp.status = "Approved"
                    ) as on_approved
                ');

                $query->selectRaw('
                    (SELECT COALESCE(COUNT(spi.id), 0)
                     FROM service_purchase_items spi
                     JOIN service_purchases sp ON spi.service_purchase_id = sp.id
                     WHERE spi.service_id = services.id
                     AND sp.status = "In Progress"
                    ) as on_progress
                ');

                $query->selectRaw('
                    (SELECT COALESCE(COUNT(spi.id), 0)
                     FROM service_purchase_items spi
                     JOIN service_purchases sp ON spi.service_purchase_id = sp.id
                     WHERE spi.service_id = services.id
                     AND sp.status = "Done"
                    ) as on_done
                ');

                $query->selectRaw('
                    (SELECT COALESCE(COUNT(spi.id), 0)
                     FROM service_purchase_items spi
                     JOIN service_purchases sp ON spi.service_purchase_id = sp.id
                     WHERE spi.service_id = services.id
                     AND sp.status = "Cancelled"
                    ) as on_cancelled
                ');

                $query->selectRaw('
                    (SELECT COALESCE(COUNT(spi.id), 0)
                     FROM service_purchase_items spi
                     JOIN service_purchases sp ON spi.service_purchase_id = sp.id
                     WHERE spi.service_id = services.id
                     AND sp.status IN ("Approved", "In Progress", "Done")
                    ) as total_purchased
                ');

                // Apply session filters
                $query = ServiceAnalyticReport::applyFiltersToQuery($query, $filters);
            })
            ->defaultSort('total_purchased', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('category_name')
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Service Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Service Name')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->name),

                Tables\Columns\TextColumn::make('price')
                    ->label('Service Price')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->formatStateUsing(fn ($state) => 'Total: Rp ' . number_format($state, 0, ',', '.')),
                    ]),

                Tables\Columns\TextColumn::make('total_purchased')
                    ->label('Total Purchased')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('on_requested')
                    ->label('Requested')
                    ->color('amber')
                    ->numeric(),

                Tables\Columns\TextColumn::make('on_approved')
                    ->label('Approved')
                    ->color('blue')
                    ->numeric(),

                Tables\Columns\TextColumn::make('on_progress')
                    ->label('In Progress')
                    ->color('purple')
                    ->numeric(),

                Tables\Columns\TextColumn::make('on_done')
                    ->label('Done')
                    ->color('success')
                    ->numeric(),

                Tables\Columns\TextColumn::make('on_cancelled')
                    ->label('Cancelled')
                    ->color('danger')
                    ->numeric(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_original')
                    ->label('View Service')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->url(fn ($record) => route('filament.admin.resources.services.view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('No Services Found')
            ->emptyStateDescription('There are no services matching your current filters.')
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
            'index' => Pages\ListServiceAnalyticReports::route('/'),
        ];
    }
}
