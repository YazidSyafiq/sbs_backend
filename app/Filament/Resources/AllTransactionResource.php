<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AllTransactionResource\Pages;
use App\Models\AllTransaction;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class AllTransactionResource extends Resource
{
    protected static ?string $model = AllTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-c-calculator';

    protected static ?string $navigationGroup = 'Accounting and Report';

    protected static ?int $navigationSort = 6;

    public static function getModelLabel(): string
    {
        return 'Transaction';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Transactions';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Form tidak diperlukan karena read-only
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('transaction_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Income' => 'success',
                        'Expense' => 'danger',
                        'PO Product' => 'blue',
                        'PO Service' => 'purple',
                        'PO Supplier' => 'warning',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('po_number')
                    ->label('PO Number')
                    ->searchable()
                    ->placeholder('N/A'),

                Tables\Columns\TextColumn::make('transaction_name')
                    ->label('Transaction Name')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('branch')
                    ->label('Branch')
                    ->searchable()
                    ->placeholder('N/A')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('user')
                    ->label('User')
                    ->searchable()
                    ->placeholder('N/A')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Completed' => 'success',
                        'Done' => 'success',
                        'Processing' => 'blue',
                        'Requested' => 'warning',
                        'Draft' => 'gray',
                        'Cancelled' => 'danger',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Paid', 'Received' => 'success',
                        'Unpaid', 'Pending' => 'danger',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('item_type')
                    ->label('Item Type')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('item_name')
                    ->label('Item Name')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('item_code')
                    ->label('Code')
                    ->searchable()
                    ->placeholder('N/A')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->formatStateUsing(function ($state) {
                        if (is_null($state)) return '-';
                        return 'Rp ' . number_format($state, 0, ',', '.');
                    })
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Amount (Cash Flow)')
                    ->formatStateUsing(function ($state) {
                        if (is_null($state) || $state == 0) return '-';
                        $prefix = $state >= 0 ? '+' : '';
                        return $prefix . 'Rp ' . number_format($state, 0, ',', '.');
                    })
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('cost_price')
                    ->label('Cost')
                    ->formatStateUsing(function ($state) {
                        if (is_null($state) || $state == 0) return '-';
                        return 'Rp ' . number_format($state, 0, ',', '.');
                    })
                    ->alignEnd()
                    ->toggleable()
                    ->visible(fn () => !Auth::user()?->hasRole('User')),

                // Kolom Receivables
                Tables\Columns\TextColumn::make('receivables')
                    ->label('Receivables')
                    ->getStateUsing(fn ($record) => $record->outstanding_amount > 0 ? $record->outstanding_amount : 0)
                    ->formatStateUsing(function ($state) {
                        if ($state == 0) return '-';
                        return 'Rp ' . number_format($state, 0, ',', '.');
                    })
                    ->color('info') // Blue color
                    ->weight('bold')
                    ->alignEnd()
                    ->toggleable(),

                // Kolom Payables
                Tables\Columns\TextColumn::make('payables')
                    ->label('Payables')
                    ->getStateUsing(fn ($record) => $record->outstanding_amount < 0 ? abs($record->outstanding_amount) : 0)
                    ->formatStateUsing(function ($state) {
                        if ($state == 0) return '-';
                        return 'Rp ' . number_format($state, 0, ',', '.');
                    })
                    ->color('danger') // Red color
                    ->weight('bold')
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('supplier_technician')
                    ->label('Supplier/Technician')
                    ->searchable()
                    ->placeholder('N/A')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(30)
                    ->placeholder('N/A')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Semua filters dipindahkan ke modal filter period
            ])
            ->actions([
                // Tidak ada actions karena read-only
            ])
            ->bulkActions([
                // Tidak ada bulk actions karena read-only
            ])
            ->defaultSort('date', 'desc')
            ->paginated(false);
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
            'index' => Pages\ListAllTransactions::route('/'),
            'create' => Pages\CreateAllTransaction::route('/create'),
            'view' => Pages\ViewAllTransaction::route('/{record}'),
            'edit' => Pages\EditAllTransaction::route('/{record}/edit'),
        ];
    }

    // Disable CRUD operations
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }
    public static function canView($record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        // Return fresh model instance to trigger newCollection
        return (new static::$model)->newQuery();
    }
}
