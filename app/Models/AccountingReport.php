<?php

namespace App\Models;

use App\Models\Income;
use App\Models\Expense;
use App\Models\PurchaseProduct;
use App\Models\ServicePurchase;
use App\Models\PurchaseProductSupplier;
use App\Models\Product;
use App\Models\Service;
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
     * Get detailed product sales analysis
     */
    public static function getProductSalesAnalysis($filters = [])
    {
        // Default to last 12 months if no filters
        if (empty($filters['date_from']) && empty($filters['date_until'])) {
            $dateFrom = Carbon::now()->subMonths(11)->startOfMonth()->toDateString();
            $dateTo = Carbon::now()->endOfMonth()->toDateString();
        } else {
            $dateFrom = !empty($filters['date_from']) ? $filters['date_from'] : Carbon::now()->subMonths(11)->startOfMonth()->toDateString();
            $dateTo = !empty($filters['date_until']) ? $filters['date_until'] : Carbon::now()->endOfMonth()->toDateString();
        }

        // Get all product sales dari PurchaseProduct yang sudah paid
        $productSales = DB::table('purchase_products')
            ->join('purchase_product_items', 'purchase_products.id', '=', 'purchase_product_items.purchase_product_id')
            ->join('products', 'purchase_product_items.product_id', '=', 'products.id')
            ->where('purchase_products.status', '!=', 'Draft')
            ->where('purchase_products.status', '!=', 'Cancelled')
            ->where('purchase_products.status_paid', 'paid')
            ->whereBetween('purchase_products.order_date', [$dateFrom, $dateTo])
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'products.code as product_code',
                DB::raw('SUM(purchase_product_items.quantity) as total_quantity_sold'),
                DB::raw('SUM(purchase_product_items.total_price) as total_revenue'),
                DB::raw('SUM(purchase_product_items.supplier_price * purchase_product_items.quantity) as total_cost'),
                DB::raw('SUM(purchase_product_items.total_price) - SUM(purchase_product_items.supplier_price * purchase_product_items.quantity) as total_profit'),
                DB::raw('COUNT(DISTINCT purchase_products.id) as total_orders')
            )
            ->groupBy('products.id', 'products.name', 'products.code')
            ->orderBy('total_revenue', 'desc')
            ->get();

        return $productSales->map(function($item) {
            $profitMargin = $item->total_revenue > 0 ? round(($item->total_profit / $item->total_revenue) * 100, 2) : 0;

            return (object)[
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_code' => $item->product_code,
                'total_quantity_sold' => (int)$item->total_quantity_sold,
                'total_revenue' => (float)$item->total_revenue,
                'total_cost' => (float)$item->total_cost,
                'total_profit' => (float)$item->total_profit,
                'profit_margin' => $profitMargin,
                'total_orders' => (int)$item->total_orders,
                'average_order_value' => $item->total_orders > 0 ? round($item->total_revenue / $item->total_orders, 2) : 0,
            ];
        });
    }

    /**
     * Get detailed service sales analysis
     */
    public static function getServiceSalesAnalysis($filters = [])
    {
        // Default to last 12 months if no filters
        if (empty($filters['date_from']) && empty($filters['date_until'])) {
            $dateFrom = Carbon::now()->subMonths(11)->startOfMonth()->toDateString();
            $dateTo = Carbon::now()->endOfMonth()->toDateString();
        } else {
            $dateFrom = !empty($filters['date_from']) ? $filters['date_from'] : Carbon::now()->subMonths(11)->startOfMonth()->toDateString();
            $dateTo = !empty($filters['date_until']) ? $filters['date_until'] : Carbon::now()->endOfMonth()->toDateString();
        }

        // Get all service sales dari ServicePurchase yang sudah paid
        $serviceSales = DB::table('service_purchases')
            ->join('service_purchase_items', 'service_purchases.id', '=', 'service_purchase_items.service_purchase_id')
            ->join('services', 'service_purchase_items.service_id', '=', 'services.id')
            ->leftJoin('technicians', 'service_purchase_items.technician_id', '=', 'technicians.id')
            ->where('service_purchases.status', '!=', 'Draft')
            ->where('service_purchases.status', '!=', 'Cancelled')
            ->where('service_purchases.status_paid', 'paid')
            ->whereBetween('service_purchases.order_date', [$dateFrom, $dateTo])
            ->select(
                'services.id as service_id',
                'services.name as service_name',
                'services.code as service_code',
                DB::raw('COUNT(service_purchase_items.id) as total_service_count'),
                DB::raw('SUM(service_purchase_items.selling_price) as total_revenue'),
                DB::raw('SUM(service_purchase_items.cost_price) as total_technician_cost'),
                DB::raw('SUM(service_purchase_items.selling_price) - SUM(service_purchase_items.cost_price) as total_profit'),
                DB::raw('COUNT(DISTINCT service_purchases.id) as total_orders'),
                DB::raw('COUNT(DISTINCT service_purchase_items.technician_id) as total_technicians_involved')
            )
            ->groupBy('services.id', 'services.name', 'services.code')
            ->orderBy('total_revenue', 'desc')
            ->get();

        return $serviceSales->map(function($item) {
            $profitMargin = $item->total_revenue > 0 ? round(($item->total_profit / $item->total_revenue) * 100, 2) : 0;

            return (object)[
                'service_id' => $item->service_id,
                'service_name' => $item->service_name,
                'service_code' => $item->service_code,
                'total_service_count' => (int)$item->total_service_count,
                'total_revenue' => (float)$item->total_revenue,
                'total_technician_cost' => (float)$item->total_technician_cost,
                'total_profit' => (float)$item->total_profit,
                'profit_margin' => $profitMargin,
                'total_orders' => (int)$item->total_orders,
                'total_technicians_involved' => (int)$item->total_technicians_involved,
                'average_order_value' => $item->total_orders > 0 ? round($item->total_revenue / $item->total_orders, 2) : 0,
                'average_service_price' => $item->total_service_count > 0 ? round($item->total_revenue / $item->total_service_count, 2) : 0,
            ];
        });
    }

    /**
     * Get comprehensive transaction details for export
     */
    public static function getTransactionDetailsForExport($filters = [])
    {
        // Default to last 12 months if no filters
        if (empty($filters['date_from']) && empty($filters['date_until'])) {
            $dateFrom = Carbon::now()->subMonths(11)->startOfMonth()->toDateString();
            $dateTo = Carbon::now()->endOfMonth()->toDateString();
        } else {
            $dateFrom = !empty($filters['date_from']) ? $filters['date_from'] : Carbon::now()->subMonths(11)->startOfMonth()->toDateString();
            $dateTo = !empty($filters['date_until']) ? $filters['date_until'] : Carbon::now()->endOfMonth()->toDateString();
        }

        $transactions = collect();

        // 1. Income transactions
        $incomes = Income::whereBetween('date', [$dateFrom, $dateTo])
            ->orderBy('date', 'desc')
            ->get();

        foreach ($incomes as $income) {
            $transactions->push([
                'transaction_type' => 'Income',
                'transaction_id' => $income->id,
                'po_number' => null,
                'transaction_name' => $income->name,
                'date' => $income->date,
                'branch' => null,
                'user' => null,
                'status' => 'Completed',
                'payment_status' => 'Paid',
                'item_type' => 'Income',
                'item_name' => $income->name,
                'item_code' => null,
                'category' => 'Income',
                'quantity' => 1,
                'unit_price' => $income->income_amount,
                'total_price' => $income->income_amount,
                'cost_price' => 0,
                'profit' => $income->income_amount,
                'profit_margin' => 100,
                'supplier_technician' => null,
                'description' => $income->description,
            ]);
        }

        // 2. Expense transactions
        $expenses = Expense::whereBetween('date', [$dateFrom, $dateTo])
            ->orderBy('date', 'desc')
            ->get();

        foreach ($expenses as $expense) {
            $transactions->push([
                'transaction_type' => 'Expense',
                'transaction_id' => $expense->id,
                'po_number' => null,
                'transaction_name' => $expense->name,
                'date' => $expense->date,
                'branch' => null,
                'user' => null,
                'status' => 'Completed',
                'payment_status' => 'Paid',
                'item_type' => 'Expense',
                'item_name' => $expense->name,
                'item_code' => null,
                'category' => 'Expense',
                'quantity' => 1,
                'unit_price' => -$expense->expense_amount,
                'total_price' => -$expense->expense_amount,
                'cost_price' => $expense->expense_amount,
                'profit' => -$expense->expense_amount,
                'profit_margin' => -100,
                'supplier_technician' => null,
                'description' => $expense->description,
            ]);
        }

        // 3. PO Product transactions (dengan detail items)
        $poProducts = PurchaseProduct::with(['items.product.category', 'user.branch'])
            ->whereNotIn('status', ['Draft', 'Cancelled'])
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->orderBy('order_date', 'desc')
            ->get();

        foreach ($poProducts as $po) {
            foreach ($po->items as $item) {
                $revenue = $po->status_paid === 'paid' ? $item->total_price : 0;
                $cost = $po->status_paid === 'paid' ? ($item->supplier_price * $item->quantity) : 0;
                $profit = $revenue - $cost;
                $profitMargin = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0;

                $transactions->push([
                    'transaction_type' => 'PO Product',
                    'transaction_id' => $po->id,
                    'po_number' => $po->po_number,
                    'transaction_name' => $po->name,
                    'date' => $po->order_date,
                    'branch' => $po->user->branch->name ?? 'No Branch',
                    'user' => $po->user->name ?? 'Unknown User',
                    'status' => $po->status,
                    'payment_status' => ucfirst($po->status_paid ?? 'Pending'),
                    'item_type' => 'Product',
                    'item_name' => $item->product->name ?? 'Unknown Product',
                    'item_code' => $item->product->code ?? 'N/A',
                    'category' => $item->product->category->name ?? 'No Category',
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $revenue,
                    'cost_price' => $item->supplier_price * $item->quantity,
                    'profit' => $profit,
                    'profit_margin' => $profitMargin,
                    'supplier_technician' => null,
                    'description' => $po->notes,
                ]);
            }
        }

        // 4. Service Purchase transactions (dengan detail items)
        $servicePos = ServicePurchase::with(['items.service.category', 'items.technician', 'user.branch'])
            ->whereNotIn('status', ['Draft', 'Cancelled'])
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->orderBy('order_date', 'desc')
            ->get();

        foreach ($servicePos as $po) {
            foreach ($po->items as $item) {
                $revenue = $po->status_paid === 'paid' ? $item->selling_price : 0;
                $cost = $po->status_paid === 'paid' ? $item->cost_price : 0;
                $profit = $revenue - $cost;
                $profitMargin = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0;

                $transactions->push([
                    'transaction_type' => 'PO Service',
                    'transaction_id' => $po->id,
                    'po_number' => $po->po_number,
                    'transaction_name' => $po->name,
                    'date' => $po->order_date,
                    'branch' => $po->user->branch->name ?? 'No Branch',
                    'user' => $po->user->name ?? 'Unknown User',
                    'status' => $po->status,
                    'payment_status' => ucfirst($po->status_paid ?? 'Pending'),
                    'item_type' => 'Service',
                    'item_name' => $item->service->name ?? 'Unknown Service',
                    'item_code' => $item->service->code ?? 'N/A',
                    'category' => $item->service->category->name ?? 'No Category',
                    'quantity' => 1,
                    'unit_price' => $item->selling_price,
                    'total_price' => $revenue,
                    'cost_price' => $item->cost_price,
                    'profit' => $profit,
                    'profit_margin' => $profitMargin,
                    'supplier_technician' => $item->technician->name ?? 'Not Assigned',
                    'description' => $po->notes,
                ]);
            }
        }

        // 5. Supplier PO transactions
        $supplierPos = PurchaseProductSupplier::with(['product.category', 'supplier', 'user'])
            ->whereNotIn('status', ['Draft', 'Cancelled'])
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->orderBy('order_date', 'desc')
            ->get();

        foreach ($supplierPos as $po) {
            $cost = $po->status_paid === 'paid' ? $po->total_amount : 0;

            $transactions->push([
                'transaction_type' => 'PO Supplier',
                'transaction_id' => $po->id,
                'po_number' => $po->po_number,
                'transaction_name' => $po->name,
                'date' => $po->order_date,
                'branch' => null,
                'user' => $po->user->name ?? 'Unknown User',
                'status' => $po->status,
                'payment_status' => ucfirst($po->status_paid ?? 'Pending'),
                'item_type' => 'Supplier Purchase',
                'item_name' => $po->product->name ?? 'Unknown Product',
                'item_code' => $po->product->code ?? 'N/A',
                'category' => $po->product->category->name ?? 'No Category',
                'quantity' => $po->quantity,
                'unit_price' => $po->unit_price,
                'total_price' => 0, // Ini adalah pengeluaran, bukan revenue
                'cost_price' => $cost,
                'profit' => -$cost, // Negative karena ini cost
                'profit_margin' => $cost > 0 ? -100 : 0,
                'supplier_technician' => $po->supplier->name ?? 'Unknown Supplier',
                'description' => $po->notes,
            ]);
        }

        return $transactions->sortByDesc('date');
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
