<?php

namespace App\Filament\Resources\AllTransactionResource\Pages;

use App\Filament\Resources\AllTransactionResource;
use App\Filament\Resources\AllTransactionResource\Widgets\AllTransactionFilterInfo;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use App\Models\AllTransaction;
use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;

class ListAllTransactions extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = AllTransactionResource::class;

    protected function getHeaderWidgets(): array
    {
        $widgets = [];

        // Only show filter info widget if filters are active
        $filters = session('all_transaction_filters', []);
        $activeFilters = collect($filters)
            ->filter(function($value) {
                if (is_array($value)) {
                    return !empty($value);
                }
                return !is_null($value) && $value !== false && $value !== '';
            });

        if ($activeFilters->count() > 0) {
            $widgets[] = AllTransactionFilterInfo::class;
        }

        return $widgets;
    }

    /**
     * Get unique users from all transaction sources
     */
    private function getAllTransactionUsers()
    {
        $users = collect();

        // Add System users for Income/Expense
        $users->push((object)[
            'name' => 'System',
            'branch_name' => null,
        ]);

        // Get users from Purchase Products
        $poUsers = User::whereHas('purchaseProducts', function($query) {
            $query->whereNotIn('status', ['Draft', 'Cancelled']);
        })->with('branch')->get();

        foreach ($poUsers as $user) {
            $users->push((object)[
                'name' => $user->name,
                'branch_name' => $user->branch ? $user->branch->name : null,
            ]);
        }

        // Get users from Service Purchases
        $serviceUsers = User::whereHas('servicePurchases', function($query) {
            $query->whereNotIn('status', ['Draft', 'Cancelled']);
        })->with('branch')->get();

        foreach ($serviceUsers as $user) {
            $users->push((object)[
                'name' => $user->name,
                'branch_name' => $user->branch ? $user->branch->name : null,
            ]);
        }

        // Get users from Supplier Purchases
        $supplierUsers = User::whereHas('purchaseProductSuppliers', function($query) {
            $query->where('status', '!=', 'Cancelled');
        })->with('branch')->get();

        foreach ($supplierUsers as $user) {
            $users->push((object)[
                'name' => $user->name,
                'branch_name' => $user->branch ? $user->branch->name : null,
            ]);
        }

        // Remove duplicates and create options array
        return $users->unique('name')
            ->sortBy('name')
            ->mapWithKeys(function ($user) {
                $label = $user->name;
                if ($user->branch_name) {
                    $label .= ' (' . $user->branch_name . ')';
                }
                return [$user->name => $label]; // Value is just the name, label includes branch
            })
            ->toArray();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_report')
                ->label('Generate Report')
                ->icon('heroicon-o-document-chart-bar')
                ->color('info')
                ->form([
                    Section::make('Report Filters')
                        ->columns(2)
                        ->schema([
                            DatePicker::make('from_date')
                                ->label('From Date')
                                ->required()
                                ->default(now()->subMonths(11)->startOfMonth()),
                            DatePicker::make('until_date')
                                ->label('Until Date')
                                ->required()
                                ->default(now()),
                            Select::make('transaction_types')
                                ->label('Transaction Types')
                                ->options([
                                    'Income' => 'Income',
                                    'Expense' => 'Expense',
                                    'SI Product' => 'SI Product',
                                    'SI Service' => 'SI Service',
                                    'PI Product (Supplier)' => 'PI Product (Supplier)',
                                ])
                                ->multiple()
                                ->placeholder('All Transaction Types'),
                            Select::make('payment_statuses')
                                ->label('Payment Status')
                                ->options([
                                    'Paid' => 'Paid',
                                    'Unpaid' => 'Unpaid',
                                    'Received' => 'Received',
                                    'Pending' => 'Pending',
                                ])
                                ->multiple()
                                ->placeholder('All Payment Status'),
                            Select::make('item_types')
                                ->label('Item Types')
                                ->options([
                                    'Income' => 'Income',
                                    'Expense' => 'Expense',
                                    'Product' => 'Product',
                                    'Service' => 'Service',
                                    'Supplier Purchase' => 'Supplier Purchase',
                                ])
                                ->multiple()
                                ->placeholder('All Item Types'),
                            Select::make('branch')
                                ->label('Branch')
                                ->options(function () {
                                    return Branch::select('id', 'name', 'code')
                                        ->get()
                                        ->mapWithKeys(function ($branch) {
                                            return [$branch->name => $branch->name . ' (' . $branch->code . ')'];
                                        })
                                        ->toArray();
                                })
                                ->searchable()
                                ->placeholder('All Branches'),
                            Select::make('user')
                                ->label('Created By User')
                                ->options(fn () => $this->getAllTransactionUsers())
                                ->searchable()
                                ->columnSpanFull()
                                ->placeholder('All Users'),
                            TextInput::make('min_amount')
                                ->label('Minimum Amount')
                                ->numeric()
                                ->prefix('Rp')
                                ->placeholder('Enter minimum amount'),
                            TextInput::make('max_amount')
                                ->label('Maximum Amount')
                                ->numeric()
                                ->prefix('Rp')
                                ->placeholder('Enter maximum amount'),
                        ])
                ])
                ->action(function (array $data) {
                    // Build query parameters
                    $params = array_filter($data, function($value) {
                        return !is_null($value) && $value !== '' && $value !== [];
                    });
                    $queryString = http_build_query($params);

                    // Open in new tab
                    return redirect()->away(route('all-transaction.report') . '?' . $queryString);
                })
                ->modalHeading('Generate All Transaction Report')
                ->modalSubmitActionLabel('Generate Report')
                ->modalWidth('3xl'),

            Actions\Action::make('filter')
                ->label('Filter Transactions')
                ->icon('heroicon-o-funnel')
                ->color('primary')
                ->modalHeading('Filter All Transactions')
                ->modalDescription('Select criteria to filter transaction data')
                ->modalWidth('3xl')
                ->fillForm(function(): array {
                    $currentFilters = session('all_transaction_filters', []);

                    // Set default dates if not set
                    if (empty($currentFilters['date_from']) && empty($currentFilters['date_until'])) {
                        $currentFilters['date_from'] = Carbon::now()->subMonths(11)->startOfMonth()->toDateString();
                        $currentFilters['date_until'] = Carbon::now()->endOfMonth()->toDateString();
                    }

                    return $currentFilters;
                })
                ->form([
                    Section::make('Date Range')
                        ->columns(2)
                        ->schema([
                            DatePicker::make('date_from')
                                ->label('From Date')
                                ->placeholder('Select start date')
                                ->required(),

                            DatePicker::make('date_until')
                                ->label('Until Date')
                                ->placeholder('Select end date')
                                ->required(),
                        ]),

                    Section::make('Transaction Filters')
                        ->columns(2)
                        ->schema([
                            Select::make('transaction_types')
                                ->label('Transaction Types')
                                ->options([
                                    'Income' => 'Income',
                                    'Expense' => 'Expense',
                                    'SI Product' => 'SI Product',
                                    'SI Service' => 'SI Service',
                                    'PI Product (Supplier)' => 'PI Product (Supplier)',
                                ])
                                ->multiple()
                                ->placeholder('All Transaction Types'),

                            Select::make('payment_statuses')
                                ->label('Payment Status')
                                ->options([
                                    'Paid' => 'Paid',
                                    'Unpaid' => 'Unpaid',
                                    'Received' => 'Received',
                                    'Pending' => 'Pending',
                                ])
                                ->multiple()
                                ->placeholder('All Payment Status'),

                            Select::make('item_types')
                                ->label('Item Types')
                                ->options([
                                    'Income' => 'Income',
                                    'Expense' => 'Expense',
                                    'Product' => 'Product',
                                    'Service' => 'Service',
                                    'Supplier Purchase' => 'Supplier Purchase',
                                ])
                                ->multiple()
                                ->placeholder('All Item Types'),

                            Select::make('branch')
                                ->label('Branch')
                                ->options(function () {
                                    return Branch::select('id', 'name', 'code')
                                        ->get()
                                        ->mapWithKeys(function ($branch) {
                                            return [$branch->name => $branch->name . ' (' . $branch->code . ')'];
                                        })
                                        ->toArray();
                                })
                                ->searchable()
                                ->placeholder('All Branches'),

                            Select::make('user')
                                ->label('Created By User')
                                ->hint('Filter transactions by who created them')
                                ->options(fn () => $this->getAllTransactionUsers())
                                ->columnSpanFull()
                                ->searchable()
                                ->placeholder('All Users'),
                        ]),
                ])
                ->action(function (array $data): void {
                    session(['all_transaction_filters' => $data]);

                    $this->redirect(static::getUrl());

                    Notification::make()
                        ->title('Filters Applied')
                        ->body('Transaction list updated with selected filters.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('reset_filters')
                ->label('Reset Filters')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    session(['all_transaction_filters' => []]);

                    $this->redirect(static::getUrl());

                    Notification::make()
                        ->title('Filters Reset')
                        ->body('Showing last 12 months transaction data.')
                        ->info()
                        ->send();
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'Transactions';
    }

    public function mount(): void
    {
        parent::mount();

        if (!session()->has('all_transaction_filters')) {
            session(['all_transaction_filters' => []]);
        }
    }
}
