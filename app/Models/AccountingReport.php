<?php

namespace App\Models;

use App\Models\Income;
use App\Models\Expense;
use App\Models\PurchaseProduct;
use App\Models\ServicePurchase;
use App\Models\PurchaseProductSupplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class AccountingReport extends Model
{
    // Gunakan table incomes sebagai base
    protected $table = 'incomes';

    protected $casts = [
        'date' => 'date',
        'income_amount' => 'decimal:2',
    ];

    // Tambahkan appends untuk virtual attributes
    protected $appends = [
        'period',
        'total_revenue',
        'total_cost',
        'profit',
        'profit_margin_percentage'
    ];

    /**
     * Get period attribute (virtual)
     */
    public function getPeriodAttribute()
    {
        // Ambil filters dari session
        $filters = session('accounting_filters', []);

        if (!empty($filters['date_from']) && !empty($filters['date_until'])) {
            $dateFrom = Carbon::parse($filters['date_from'])->format('d M Y');
            $dateTo = Carbon::parse($filters['date_until'])->format('d M Y');
            return "{$dateFrom} - {$dateTo}";
        }

        return 'Last 12 Months';
    }

    /**
     * Get total revenue attribute (virtual)
     */
    public function getTotalRevenueAttribute()
    {
        $filters = session('accounting_filters', []);
        $overview = static::getAccountingOverview($filters);
        return $overview->total_revenue;
    }

    /**
     * Get total cost attribute (virtual)
     */
    public function getTotalCostAttribute()
    {
        $filters = session('accounting_filters', []);
        $overview = static::getAccountingOverview($filters);
        return $overview->total_cost;
    }

    /**
     * Get profit attribute (virtual)
     */
    public function getProfitAttribute()
    {
        $filters = session('accounting_filters', []);
        $overview = static::getAccountingOverview($filters);
        return $overview->gross_profit;
    }

    /**
     * Get profit margin percentage attribute (virtual)
     */
    public function getProfitMarginPercentageAttribute()
    {
        $filters = session('accounting_filters', []);
        $overview = static::getAccountingOverview($filters);
        return $overview->profit_margin;
    }

    /**
     * Override newCollection to create single virtual record
     */
    public function newCollection(array $models = [])
    {
        // Buat satu virtual record untuk ditampilkan di table
        $virtualRecord = new static();
        $virtualRecord->id = 1;
        $virtualRecord->exists = true;

        return new Collection([$virtualRecord]);
    }

    /**
     * Helper method to apply filters to any query
     */
    public static function applyFiltersToQuery($query, $filters = [])
    {
        // Date filters
        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_until'])) {
            $query->whereDate('date', '<=', $filters['date_until']);
        }

        return $query;
    }

    /**
     * Get comprehensive accounting overview
     */
    public static function getAccountingOverview($filters = [])
    {
        // Default to last 12 months if no filters
        if (empty($filters['date_from']) && empty($filters['date_until'])) {
            $dateFrom = Carbon::now()->subMonths(11)->startOfMonth()->toDateString();
            $dateTo = Carbon::now()->endOfMonth()->toDateString();
        } else {
            $dateFrom = !empty($filters['date_from']) ? $filters['date_from'] : Carbon::now()->subMonths(11)->startOfMonth()->toDateString();
            $dateTo = !empty($filters['date_until']) ? $filters['date_until'] : Carbon::now()->endOfMonth()->toDateString();
        }

        // Total Revenue dari berbagai sumber
        $incomeRevenue = Income::whereBetween('date', [$dateFrom, $dateTo])->sum('income_amount');

        // Revenue dari PO Products yang sudah paid
        $productRevenue = PurchaseProduct::with('items')
            ->whereNotIn('status', ['Draft', 'Cancelled'])
            ->where('status_paid', 'paid')
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->get()
            ->flatMap->items
            ->sum('total_price');

        // Revenue dari Service Purchase yang sudah paid
        $serviceRevenue = ServicePurchase::with('items')
            ->whereNotIn('status', ['Draft', 'Cancelled'])
            ->where('status_paid', 'paid')
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->get()
            ->flatMap->items
            ->sum('selling_price');

        $totalRevenue = $incomeRevenue + $productRevenue + $serviceRevenue;

        // Total Cost dari berbagai sumber
        $expenseCost = Expense::whereBetween('date', [$dateFrom, $dateTo])->sum('expense_amount');

        // Cost dari PO Products (supplier price)
        $productCost = PurchaseProduct::with('items')
            ->whereNotIn('status', ['Draft', 'Cancelled'])
            ->where('status_paid', 'paid')
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->get()
            ->flatMap->items
            ->sum(function($item) {
                return $item->supplier_price * $item->quantity;
            });

        // Cost dari Service Purchase (technician cost)
        $serviceCost = ServicePurchase::with('items')
            ->whereNotIn('status', ['Draft', 'Cancelled'])
            ->where('status_paid', 'paid')
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->get()
            ->flatMap->items
            ->sum('cost_price');

        // Cost dari Supplier PO
        $supplierCost = PurchaseProductSupplier::whereNotIn('status', ['Draft', 'Cancelled'])
            ->where('status_paid', 'paid')
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->sum('total_amount');

        $totalCost = $expenseCost + $productCost + $serviceCost + $supplierCost;

        $grossProfit = $totalRevenue - $totalCost;
        $profitMargin = $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0;

        return (object)[
            'total_revenue' => $totalRevenue,
            'income_revenue' => $incomeRevenue,
            'product_revenue' => $productRevenue,
            'service_revenue' => $serviceRevenue,
            'total_cost' => $totalCost,
            'expense_cost' => $expenseCost,
            'product_cost' => $productCost,
            'service_cost' => $serviceCost,
            'supplier_cost' => $supplierCost,
            'gross_profit' => $grossProfit,
            'profit_margin' => $profitMargin,
            'period_from' => $dateFrom,
            'period_to' => $dateTo,
        ];
    }

    /**
    * Get comprehensive cash flow analysis - CORRECTED VERSION
    */
    public static function getCashFlowAnalysis($filters = [])
    {
        // Default to last 12 months if no filters
        if (empty($filters['date_from']) && empty($filters['date_until'])) {
            $dateFrom = Carbon::now()->subMonths(11)->startOfMonth()->toDateString();
            $dateTo = Carbon::now()->endOfMonth()->toDateString();
        } else {
            $dateFrom = !empty($filters['date_from']) ? $filters['date_from'] : Carbon::now()->subMonths(11)->startOfMonth()->toDateString();
            $dateTo = !empty($filters['date_until']) ? $filters['date_until'] : Carbon::now()->endOfMonth()->toDateString();
        }

        // ACTUAL CASH FLOW (kas yang benar-benar masuk/keluar)

        // CASH IN = Uang yang masuk ke kita
        $actualCashIn = 0;

        // 1. Income langsung
        $actualCashIn += Income::whereBetween('date', [$dateFrom, $dateTo])->sum('income_amount');

        // 2. Uang dari customer untuk PO Products (yang sudah paid)
        $actualCashIn += PurchaseProduct::whereNotIn('status', ['Draft', 'Cancelled'])
            ->where('status_paid', 'paid')
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->sum('total_amount'); // Uang yang masuk dari customer

        // 3. Uang dari customer untuk Services (yang sudah paid)
        $actualCashIn += ServicePurchase::whereNotIn('status', ['Draft', 'Cancelled'])
            ->where('status_paid', 'paid')
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->sum('total_amount'); // Uang yang masuk dari customer

        // CASH OUT = Uang yang keluar dari kita
        $actualCashOut = 0;

        // 1. Expenses langsung
        $actualCashOut += Expense::whereBetween('date', [$dateFrom, $dateTo])->sum('expense_amount');

        // 2. Uang yang kita bayar ke Supplier (untuk product)
        $actualCashOut += PurchaseProductSupplier::whereNotIn('status', ['Draft', 'Cancelled'])
            ->where('status_paid', 'paid')
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->sum('total_amount'); // Uang yang keluar ke supplier

        // 3. Uang yang kita bayar ke Teknisi (untuk service)
        // Ini perlu dicari dari ServicePurchase yang sudah paid, tapi ambil cost_price nya
        $serviceCashOut = ServicePurchase::with('items')
            ->whereNotIn('status', ['Draft', 'Cancelled'])
            ->where('status_paid', 'paid')
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->get()
            ->flatMap->items
            ->sum('cost_price'); // Yang dibayar ke teknisi

        $actualCashOut += $serviceCashOut;

        $netActualCashFlow = $actualCashIn - $actualCashOut;

        // OUTSTANDING BALANCES
        $outstandingReceivables = 0;
        $outstandingReceivables += PurchaseProduct::whereNotIn('status', ['Draft', 'Cancelled'])
            ->where('status_paid', 'unpaid')
            ->sum('total_amount');
        $outstandingReceivables += ServicePurchase::whereNotIn('status', ['Draft', 'Cancelled'])
            ->where('status_paid', 'unpaid')
            ->sum('total_amount');

        $outstandingPayables = 0;
        $outstandingPayables += PurchaseProductSupplier::whereNotIn('status', ['Draft', 'Cancelled'])
            ->where('status_paid', 'unpaid')
            ->sum('total_amount');

        $netOutstandingBalance = $outstandingReceivables - $outstandingPayables;
        $totalNetPosition = $netActualCashFlow + $netOutstandingBalance;

        return (object)[
            'actual_cash_in' => $actualCashIn,
            'actual_cash_out' => $actualCashOut,
            'net_actual_cash_flow' => $netActualCashFlow,
            'outstanding_receivables' => $outstandingReceivables,
            'outstanding_payables' => $outstandingPayables,
            'net_outstanding_balance' => $netOutstandingBalance,
            'total_net_position' => $totalNetPosition,

            // Keep legacy fields for backward compatibility
            'projected_cash_in' => $outstandingReceivables,
            'projected_cash_out' => $outstandingPayables,
            'net_projected_cash_flow' => $netOutstandingBalance,
            'total_net_cash_flow' => $totalNetPosition,
        ];
    }

    /**
     * Get debt analysis
     */
    public static function getDebtAnalysis($filters = [])
    {
        // Outstanding dari PO Products
        $productOutstanding = PurchaseProduct::whereNotIn('status', ['Draft', 'Cancelled'])
            ->where('status_paid', 'unpaid')
            ->sum('total_amount');

        // Outstanding dari Service Purchase
        $serviceOutstanding = ServicePurchase::whereNotIn('status', ['Draft', 'Cancelled'])
            ->where('status_paid', 'unpaid')
            ->sum('total_amount');

        // Outstanding dari Supplier PO
        $supplierOutstanding = PurchaseProductSupplier::whereNotIn('status', ['Draft', 'Cancelled'])
            ->where('status_paid', 'unpaid')
            ->sum('total_amount');

        // Debt to suppliers (yang harus dibayar ke supplier)
        $debtToSuppliers = $supplierOutstanding;

        // Receivables (yang harus diterima dari customer)
        $receivables = $productOutstanding + $serviceOutstanding;

        $netDebt = $debtToSuppliers - $receivables;

        return (object)[
            'total_outstanding' => $productOutstanding + $serviceOutstanding + $supplierOutstanding,
            'receivables_from_customers' => $receivables,
            'debt_to_suppliers' => $debtToSuppliers,
            'net_debt_position' => $netDebt,
            'product_outstanding' => $productOutstanding,
            'service_outstanding' => $serviceOutstanding,
            'supplier_outstanding' => $supplierOutstanding,
        ];
    }

    /**
     * Get monthly profit trends - FIXED VERSION
     */
    public static function getMonthlyProfitTrends($filters = [])
    {
        // Jika ada filter tanggal, gunakan range tersebut
        if (!empty($filters['date_from']) && !empty($filters['date_until'])) {
            $startDate = Carbon::parse($filters['date_from']);
            $endDate = Carbon::parse($filters['date_until']);
            $diffInDays = $startDate->diffInDays($endDate);

            // Determine grouping based on date range
            if ($diffInDays <= 31) {
                // Daily grouping for ranges <= 31 days
                $results = collect();
                for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                    $monthFilters = [
                        'date_from' => $date->toDateString(),
                        'date_until' => $date->toDateString(),
                    ];
                    $monthData = static::getAccountingOverview($monthFilters);
                    $results->push((object)[
                        'period' => $date->format('Y-m-d'),
                        'period_name' => $date->format('d M'),
                        'total_revenue' => $monthData->total_revenue,
                        'total_cost' => $monthData->total_cost,
                        'gross_profit' => $monthData->gross_profit,
                        'profit_margin' => $monthData->profit_margin,
                    ]);
                }
                return $results;
            } elseif ($diffInDays <= 365) {
                // Weekly grouping for ranges <= 365 days
                $results = collect();
                for ($date = $startDate->copy()->startOfWeek(); $date->lte($endDate); $date->addWeek()) {
                    $weekEnd = $date->copy()->endOfWeek();
                    if ($weekEnd->gt($endDate)) $weekEnd = $endDate;

                    $monthFilters = [
                        'date_from' => $date->toDateString(),
                        'date_until' => $weekEnd->toDateString(),
                    ];
                    $monthData = static::getAccountingOverview($monthFilters);
                    $results->push((object)[
                        'period' => $date->format('Y-m-d'),
                        'period_name' => 'W' . $date->weekOfYear . ' ' . $date->format('M Y'),
                        'total_revenue' => $monthData->total_revenue,
                        'total_cost' => $monthData->total_cost,
                        'gross_profit' => $monthData->gross_profit,
                        'profit_margin' => $monthData->profit_margin,
                    ]);
                }
                return $results;
            } else {
                // Monthly grouping for ranges > 365 days
                $startDate = $startDate->startOfMonth();
                $endDate = $endDate->endOfMonth();
            }
        } else {
            // Default: 12 bulan terakhir termasuk bulan sekarang
            $startDate = Carbon::now()->subMonths(11)->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();
        }

        // Generate monthly data - ALWAYS include all months in range
        $results = collect();
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $monthStart = $current->copy()->startOfMonth()->toDateString();
            $monthEnd = $current->copy()->endOfMonth()->toDateString();

            $monthFilters = [
                'date_from' => $monthStart,
                'date_until' => $monthEnd,
            ];

            $monthData = static::getAccountingOverview($monthFilters);

            $results->push((object)[
                'period' => $current->format('Y-m'),
                'period_name' => $current->format('M Y'),
                'total_revenue' => $monthData->total_revenue,
                'total_cost' => $monthData->total_cost,
                'gross_profit' => $monthData->gross_profit,
                'profit_margin' => $monthData->profit_margin,
            ]);

            $current->addMonth();
        }

        return $results;
    }

    /**
     * Get revenue breakdown
     */
    public static function getRevenueBreakdown($filters = [])
    {
        $overview = static::getAccountingOverview($filters);

        return (object)[
            'income_revenue' => $overview->income_revenue,
            'product_revenue' => $overview->product_revenue,
            'service_revenue' => $overview->service_revenue,
            'total_revenue' => $overview->total_revenue,
            'income_percentage' => $overview->total_revenue > 0 ? round(($overview->income_revenue / $overview->total_revenue) * 100, 1) : 0,
            'product_percentage' => $overview->total_revenue > 0 ? round(($overview->product_revenue / $overview->total_revenue) * 100, 1) : 0,
            'service_percentage' => $overview->total_revenue > 0 ? round(($overview->service_revenue / $overview->total_revenue) * 100, 1) : 0,
        ];
    }

    /**
     * Get cost breakdown
     */
    public static function getCostBreakdown($filters = [])
    {
        $overview = static::getAccountingOverview($filters);

        return (object)[
            'expense_cost' => $overview->expense_cost,
            'product_cost' => $overview->product_cost,
            'service_cost' => $overview->service_cost,
            'supplier_cost' => $overview->supplier_cost,
            'total_cost' => $overview->total_cost,
            'expense_percentage' => $overview->total_cost > 0 ? round(($overview->expense_cost / $overview->total_cost) * 100, 1) : 0,
            'product_percentage' => $overview->total_cost > 0 ? round(($overview->product_cost / $overview->total_cost) * 100, 1) : 0,
            'service_percentage' => $overview->total_cost > 0 ? round(($overview->service_cost / $overview->total_cost) * 100, 1) : 0,
            'supplier_percentage' => $overview->total_cost > 0 ? round(($overview->supplier_cost / $overview->total_cost) * 100, 1) : 0,
        ];
    }

    /**
     * Get period label
     */
    public static function getPeriodLabel($filters = [])
    {
        if (!empty($filters['date_from']) || !empty($filters['date_until'])) {
            $dateRange = '';
            if (!empty($filters['date_from'])) {
                $dateRange .= 'From ' . Carbon::parse($filters['date_from'])->format('d M Y');
            }
            if (!empty($filters['date_until'])) {
                if ($dateRange) $dateRange .= ' ';
                $dateRange .= 'To ' . Carbon::parse($filters['date_until'])->format('d M Y');
            }
            return $dateRange;
        }

        return 'Last 12 Months';
    }

    /**
     * Check if filters are active
     */
    public static function hasActiveFilters($filters = [])
    {
        return collect($filters)->filter(function($value) {
            if (is_array($value)) {
                return !empty($value);
            }
            return !is_null($value) && $value !== false && $value !== '';
        })->count() > 0;
    }
}
