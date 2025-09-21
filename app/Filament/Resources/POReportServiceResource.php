<?php

namespace App\Filament\Resources;

use App\Filament\Resources\POReportServiceResource\Pages;
use App\Models\POReportService;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;
use Auth;

class POReportServiceResource extends Resource
{
    protected static ?string $model = POReportService::class;

    protected static ?string $navigationIcon = 'heroicon-m-chart-bar';

    public static function getNavigationGroup(): ?string
    {
        $user = Auth::user();

        if ($user && $user->hasRole('User')) {
            return 'Purchase Service Management';
        }

        return 'Service Sales Management';
    }

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        $user = Auth::user();
        if ($user && $user->hasRole('User')) {
            return 'Purchase Service Analytic';
        }
        return 'Service Sales Analytic';}

    public static function getPluralModelLabel(): string
    {
        $user = Auth::user();
        if ($user && $user->hasRole('User')) {
            return 'Purchase Service Analytics';
        }
        return 'Service Sales Analytics';
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $isUserRole = $user && $user->hasRole('User');

        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = Auth::user();
                $filters = session('po_service_filters', []);

                // Join dengan user dan branch untuk data lengkap
                $query->select([
                    'service_purchases.*',
                    'users.name as user_name',
                    'branches.name as branch_name',
                    'branches.code as branch_code',
                ])
                ->leftJoin('users', 'service_purchases.user_id', '=', 'users.id')
                ->leftJoin('branches', 'users.branch_id', '=', 'branches.id');

                // Use existing scopes
                $query->activeOnly(); // Exclude draft and cancelled

                // Apply session filters
                if (!empty($filters['branch_id'])) {
                    $query->where('users.branch_id', $filters['branch_id']);
                }

                if (!empty($filters['type_po'])) {
                    $query->whereIn('service_purchases.type_po', $filters['type_po']);
                }

                if (!empty($filters['status'])) {
                    $query->whereIn('service_purchases.status', $filters['status']);
                }

                if (!empty($filters['status_paid'])) {
                    $query->whereIn('service_purchases.status_paid', $filters['status_paid']);
                }

                if (!empty($filters['technician_id'])) {
                    $query->whereHas('items', function($q) use ($filters) {
                        $q->where('technician_id', $filters['technician_id']);
                    });
                }

                if (!empty($filters['date_from'])) {
                    $query->whereDate('service_purchases.order_date', '>=', $filters['date_from']);
                }

                if (!empty($filters['date_until'])) {
                    $query->whereDate('service_purchases.order_date', '<=', $filters['date_until']);
                }

                if (!empty($filters['outstanding_only'])) {
                    $query->where('service_purchases.status_paid', 'unpaid');
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
                        'Approved' => 'blue',
                        'In Progress' => 'purple',
                        'Done' => 'success',
                        default => 'slate'
                    }),

                Tables\Columns\TextColumn::make('status_paid')
                    ->label('Payment Status')
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
                    ->label($isUserRole ? 'Outstanding Payment' : 'Outstanding')
                    ->state(function ($record) {
                        return $record->status_paid === 'unpaid' ? $record->total_amount : 0;
                    })
                    ->formatStateUsing(fn ($state) => $state > 0 ? 'Rp ' . number_format($state, 0, ',', '.') : '-')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label($isUserRole ? 'Paid Amount' : 'Amount Received')
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
                    ->url(fn ($record) => route('filament.admin.resources.service-purchases.view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('No Service Purchase Orders Found')
            ->emptyStateDescription('There are no service purchase orders matching your current filters.')
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
            'index' => Pages\ListPOReportServices::route('/'),
        ];
    }
}
