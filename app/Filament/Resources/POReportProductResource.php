<?php

namespace App\Filament\Resources;

use App\Filament\Resources\POReportProductResource\Pages;
use App\Filament\Resources\POReportProductResource\RelationManagers;
use App\Models\POReportProduct;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;
use Auth;

class POReportProductResource extends Resource
{
    protected static ?string $model = POReportProduct::class;

    protected static ?string $navigationIcon = 'heroicon-m-chart-bar';

    protected static ?string $navigationGroup = 'Purchase Product Management';

    protected static ?int $navigationSort = 13;

    public static function getModelLabel(): string
    {
        return 'Purchase Product Analytic';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Purchase Product Analytics';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = Auth::user();
                $filters = session('po_product_filters', []);

                // Join dengan user dan branch untuk data lengkap
                $query->select([
                    'purchase_products.*',
                    'users.name as user_name',
                    'branches.name as branch_name',
                    'branches.code as branch_code',
                ])
                ->leftJoin('users', 'purchase_products.user_id', '=', 'users.id')
                ->leftJoin('branches', 'users.branch_id', '=', 'branches.id');

                // Use existing scopes
                $query->activeOnly(); // Exclude draft and cancelled

                // Apply session filters
                if (!empty($filters['branch_id'])) {
                    $query->where('users.branch_id', $filters['branch_id']);
                }

                if (!empty($filters['type_po'])) {
                    $query->whereIn('purchase_products.type_po', $filters['type_po']);
                }

                if (!empty($filters['status'])) {
                    $query->whereIn('purchase_products.status', $filters['status']);
                }

                if (!empty($filters['status_paid'])) {
                    $query->whereIn('purchase_products.status_paid', $filters['status_paid']);
                }

                if (!empty($filters['date_from'])) {
                    $query->whereDate('purchase_products.order_date', '>=', $filters['date_from']);
                }

                if (!empty($filters['date_until'])) {
                    $query->whereDate('purchase_products.order_date', '<=', $filters['date_until']);
                }

                if (!empty($filters['outstanding_only'])) {
                    $query->where('purchase_products.status_paid', 'unpaid');
                }

                // Role-based filtering using existing scope
                if ($user->hasRole('User')) {
                    $query->byBranch($user->branch_id);
                }
            })
            ->defaultSort('order_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('branch_name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('po_number')
                    ->label('PO Number')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('PO Name')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->name),

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

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.')),
                    ]),

                Tables\Columns\TextColumn::make('outstanding_debt')
                    ->label('Outstanding')
                    ->state(function ($record) {
                        return $record->status_paid === 'unpaid' ? $record->total_amount : 0;
                    })
                    ->formatStateUsing(fn ($state) => $state > 0 ? 'Rp ' . number_format($state, 0, ',', '.') : '-')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Paid Amount')
                    ->state(function ($record) {
                        return $record->status_paid === 'paid' ? $record->total_amount : 0;
                    })
                    ->formatStateUsing(fn ($state) => $state > 0 ? 'Rp ' . number_format($state, 0, ',', '.') : '-')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('order_date')
                    ->label('Order Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user_name')
                    ->label('Requested By')
                    ->searchable()
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_original')
                    ->label('View Original PO')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->url(fn ($record) => route('filament.admin.resources.purchase-products.view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('No Purchase Orders Found')
            ->emptyStateDescription('There are no purchase orders matching your current filters.')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('accounting_notice')
                ->label('')
                ->content('This is a read-only accounting report. No editing is allowed.')
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
            'index' => Pages\ListPOReportProducts::route('/'),
        ];
    }
}
