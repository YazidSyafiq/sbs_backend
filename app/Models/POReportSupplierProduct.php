<?php

namespace App\Models;

use App\Models\PurchaseProductSupplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class POReportSupplierProduct extends Model
{
    // Gunakan table yang sama dengan PurchaseProductSupplier
    protected $table = 'purchase_product_suppliers';

    // Cast untuk data types
    protected $casts = [
        'order_date' => 'date',
        'received_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Relasi ke User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke Supplier
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Relasi ke Product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope untuk exclude draft dan cancelled
     */
    public function scopeActiveOnly($query)
    {
        return $query->whereNotIn('status', ['Draft', 'Cancelled']);
    }

    /**
     * Scope untuk outstanding debt only
     */
    public function scopeWithOutstandingDebt($query)
    {
        return $query->where('status_paid', 'unpaid');
    }

    /**
     * Helper method to apply filters to any query
     */
    public static function applyFiltersToQuery($query, $filters = [])
    {
        // Supplier filter
        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        // Product filter
        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        // Product category filter
        if (!empty($filters['category_id'])) {
            $query->whereHas('product', function($q) use ($filters) {
                $q->where('category_id', $filters['category_id']);
            });
        }

        // Type filter
        if (!empty($filters['type_po'])) {
            $query->whereIn('type_po', $filters['type_po']);
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        // Payment status filter
        if (!empty($filters['status_paid'])) {
            $query->whereIn('status_paid', $filters['status_paid']);
        }

        // Date filters
        if (!empty($filters['date_from'])) {
            $query->whereDate('order_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_until'])) {
            $query->whereDate('order_date', '<=', $filters['date_until']);
        }

        // Outstanding only filter
        if (!empty($filters['outstanding_only'])) {
            $query->where('status_paid', 'unpaid');
        }

        return $query;
    }

    /**
     * Get filter summary for display
     */
    public static function getFilterSummary($filters = [])
    {
        $summary = [];

        if (!empty($filters['supplier_id'])) {
            $supplierName = Supplier::find($filters['supplier_id'])->name ?? 'Unknown Supplier';
            $summary[] = "Supplier: {$supplierName}";
        }

        if (!empty($filters['product_id'])) {
            $productName = Product::find($filters['product_id'])->name ?? 'Unknown Product';
            $summary[] = "Product: {$productName}";
        }

        if (!empty($filters['category_id'])) {
            $categoryName = ProductCategory::find($filters['category_id'])->name ?? 'Unknown Category';
            $summary[] = "Category: {$categoryName}";
        }

        if (!empty($filters['type_po'])) {
            $summary[] = "Type: " . implode(', ', array_map('ucfirst', $filters['type_po']));
        }

        if (!empty($filters['status'])) {
            $summary[] = "Status: " . implode(', ', $filters['status']);
        }

        if (!empty($filters['status_paid'])) {
            $summary[] = "Payment: " . implode(', ', array_map('ucfirst', $filters['status_paid']));
        }

        if (!empty($filters['date_from']) || !empty($filters['date_until'])) {
            $dateRange = "Date: ";
            if (!empty($filters['date_from'])) {
                $dateRange .= "from " . Carbon::parse($filters['date_from'])->format('d M Y');
            }
            if (!empty($filters['date_until'])) {
                if (str_contains($dateRange, 'from')) $dateRange .= " ";
                $dateRange .= "to " . Carbon::parse($filters['date_until'])->format('d M Y');
            }
            $summary[] = $dateRange;
        }

        if (!empty($filters['outstanding_only'])) {
            $summary[] = "Outstanding payments only";
        }

        return $summary;
    }

    /**
     * Get filtered overview statistics with supplier-product focus
     */
    public static function getFilteredOverviewStats($filters = [])
    {
        $query = static::with(['supplier', 'product'])->activeOnly();

        // Apply filters
        $query = static::applyFiltersToQuery($query, $filters);

        $allPos = $query->get();

        $paidPos = $allPos->filter(fn($po) => $po->status_paid === 'paid');
        $unpaidPos = $allPos->filter(fn($po) => $po->status_paid === 'unpaid');

        $totalPosAmount = $allPos->sum('total_amount');
        $paidAmount = $paidPos->sum('total_amount');
        $outstandingDebt = $unpaidPos->sum('total_amount');

        // Calculate payment rate
        $paymentRate = $totalPosAmount > 0 ? round(($paidAmount / $totalPosAmount) * 100, 1) : 0;

        // Supplier statistics
        $uniqueSuppliers = $allPos->pluck('supplier_id')->unique()->count();
        $topSupplierPos = $allPos->groupBy('supplier_id')->sortByDesc(function($group) {
            return $group->sum('total_amount');
        })->first();
        $topSupplierValue = $topSupplierPos ? $topSupplierPos->sum('total_amount') : 0;

        // Product statistics
        $uniqueProducts = $allPos->pluck('product_id')->unique()->count();
        $totalQuantity = $allPos->sum('quantity');
        $topProductPos = $allPos->groupBy('product_id')->sortByDesc(function($group) {
            return $group->sum('quantity');
        })->first();
        $topProductQuantity = $topProductPos ? $topProductPos->sum('quantity') : 0;

        // Credit statistics
        $creditPos = $allPos->where('type_po', 'credit');
        $creditPaid = $creditPos->filter(fn($po) => $po->status_paid === 'paid');
        $creditTotalAmount = $creditPos->sum('total_amount');
        $creditPaidAmount = $creditPaid->sum('total_amount');
        $creditOutstanding = $creditPos->filter(fn($po) => $po->status_paid === 'unpaid')->sum('total_amount');
        $creditPaymentRate = $creditTotalAmount > 0 ? round(($creditPaidAmount / $creditTotalAmount) * 100, 1) : 0;

        // Cash statistics
        $cashPos = $allPos->where('type_po', 'cash');
        $cashPaid = $cashPos->filter(fn($po) => $po->status_paid === 'paid');
        $cashTotalAmount = $cashPos->sum('total_amount');
        $cashPaidAmount = $cashPaid->sum('total_amount');
        $cashOutstanding = $cashPos->filter(fn($po) => $po->status_paid === 'unpaid')->sum('total_amount');
        $cashPaymentRate = $cashTotalAmount > 0 ? round(($cashPaidAmount / $cashTotalAmount) * 100, 1) : 0;

        return (object)[
            'total_count' => $allPos->count(),
            'total_po_amount' => $totalPosAmount,
            'paid_amount' => $paidAmount,
            'outstanding_debt' => $outstandingDebt,
            'payment_rate' => $paymentRate,

            // Supplier metrics
            'unique_suppliers' => $uniqueSuppliers,
            'top_supplier_value' => $topSupplierValue,

            // Product metrics
            'unique_products' => $uniqueProducts,
            'total_quantity' => $totalQuantity,
            'top_product_quantity' => $topProductQuantity,
            'average_order_value' => $allPos->count() > 0 ? round($totalPosAmount / $allPos->count(), 2) : 0,

            // Type counts
            'credit_count' => $creditPos->count(),
            'cash_count' => $cashPos->count(),

            // Credit breakdown
            'credit_total_amount' => $creditTotalAmount,
            'credit_paid_amount' => $creditPaidAmount,
            'credit_outstanding' => $creditOutstanding,
            'credit_payment_rate' => $creditPaymentRate,

            // Cash breakdown
            'cash_total_amount' => $cashTotalAmount,
            'cash_paid_amount' => $cashPaidAmount,
            'cash_outstanding' => $cashOutstanding,
            'cash_payment_rate' => $cashPaymentRate,
        ];
    }

    /**
     * Get filtered monthly trends with supplier-product focus
     */
    public static function getFilteredMonthlyTrends($filters = [])
    {
        // Determine date range based on filters
        if (!empty($filters['date_from']) || !empty($filters['date_until'])) {
            $startDate = !empty($filters['date_from']) ? Carbon::parse($filters['date_from']) : Carbon::now()->subYear();
            $endDate = !empty($filters['date_until']) ? Carbon::parse($filters['date_until']) : Carbon::now();

            $query = PurchaseProductSupplier::query()
                ->whereNotIn('status', ['Draft', 'Cancelled'])
                ->whereDate('order_date', '>=', $startDate)
                ->whereDate('order_date', '<=', $endDate);
        } else {
            // Default: last 12 months
            $startDate = Carbon::now()->subMonths(11)->startOfMonth();
            $endDate = Carbon::now();

            $query = PurchaseProductSupplier::query()
                ->whereNotIn('status', ['Draft', 'Cancelled'])
                ->where('order_date', '>=', $startDate);
        }

        // Apply other filters (except date filters)
        $filtersWithoutDate = $filters;
        unset($filtersWithoutDate['date_from'], $filtersWithoutDate['date_until']);
        $query = static::applyFiltersToQuery($query, $filtersWithoutDate);

        $allPos = $query->get();

        // Group by appropriate period
        if (!empty($filters['date_from']) || !empty($filters['date_until'])) {
            $diffInDays = $startDate->diffInDays($endDate);

            if ($diffInDays <= 31) {
                $groupBy = 'Y-m-d';
            } elseif ($diffInDays <= 365) {
                $groupBy = function($po) {
                    return $po->order_date->startOfWeek()->format('Y-m-d');
                };
            } else {
                $groupBy = 'Y-m';
            }
        } else {
            $groupBy = 'Y-m';
        }

        if (is_string($groupBy)) {
            $monthlyGroups = $allPos->groupBy(function($po) use ($groupBy) {
                return $po->order_date->format($groupBy);
            });
        } else {
            $monthlyGroups = $allPos->groupBy($groupBy);
        }

        $results = collect();

        // Generate periods
        if (!empty($filters['date_from']) || !empty($filters['date_until'])) {
            $diffInDays = $startDate->diffInDays($endDate);

            if ($diffInDays <= 31) {
                // Daily periods
                for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                    $key = $date->format('Y-m-d');
                    $periodPos = $monthlyGroups->get($key, collect());

                    $totalAmount = $periodPos->sum('total_amount');
                    $paidAmount = $periodPos->filter(fn($po) => $po->status_paid === 'paid')->sum('total_amount');
                    $outstandingAmount = $periodPos->filter(fn($po) => $po->status_paid === 'unpaid')->sum('total_amount');
                    $totalQuantity = $periodPos->sum('quantity');

                    $results->push((object)[
                        'period' => $key,
                        'period_name' => $date->format('d M'),
                        'total_pos' => $periodPos->count(),
                        'total_po_amount' => $totalAmount,
                        'paid_amount' => $paidAmount,
                        'outstanding_debt' => $outstandingAmount,
                        'total_quantity' => $totalQuantity,
                        'unique_suppliers' => $periodPos->pluck('supplier_id')->unique()->count(),
                        'unique_products' => $periodPos->pluck('product_id')->unique()->count(),
                        'payment_rate' => $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 1) : 0,
                        'credit_pos' => $periodPos->where('type_po', 'credit')->count(),
                        'cash_pos' => $periodPos->where('type_po', 'cash')->count(),
                    ]);
                }
            } elseif ($diffInDays <= 365) {
                // Weekly periods
                for ($date = $startDate->copy()->startOfWeek(); $date->lte($endDate); $date->addWeek()) {
                    $key = $date->format('Y-m-d');
                    $periodPos = $monthlyGroups->get($key, collect());

                    $totalAmount = $periodPos->sum('total_amount');
                    $paidAmount = $periodPos->filter(fn($po) => $po->status_paid === 'paid')->sum('total_amount');
                    $outstandingAmount = $periodPos->filter(fn($po) => $po->status_paid === 'unpaid')->sum('total_amount');
                    $totalQuantity = $periodPos->sum('quantity');

                    $results->push((object)[
                        'period' => $key,
                        'period_name' => 'W' . $date->weekOfYear . ' ' . $date->format('M Y'),
                        'total_pos' => $periodPos->count(),
                        'total_po_amount' => $totalAmount,
                        'paid_amount' => $paidAmount,
                        'outstanding_debt' => $outstandingAmount,
                        'total_quantity' => $totalQuantity,
                        'unique_suppliers' => $periodPos->pluck('supplier_id')->unique()->count(),
                        'unique_products' => $periodPos->pluck('product_id')->unique()->count(),
                        'payment_rate' => $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 1) : 0,
                        'credit_pos' => $periodPos->where('type_po', 'credit')->count(),
                        'cash_pos' => $periodPos->where('type_po', 'cash')->count(),
                    ]);
                }
            } else {
                // Monthly periods
                for ($date = $startDate->copy()->startOfMonth(); $date->lte($endDate); $date->addMonth()) {
                    $key = $date->format('Y-m');
                    $periodPos = $monthlyGroups->get($key, collect());

                    $totalAmount = $periodPos->sum('total_amount');
                    $paidAmount = $periodPos->filter(fn($po) => $po->status_paid === 'paid')->sum('total_amount');
                    $outstandingAmount = $periodPos->filter(fn($po) => $po->status_paid === 'unpaid')->sum('total_amount');
                    $totalQuantity = $periodPos->sum('quantity');

                    $results->push((object)[
                        'period' => $key,
                        'period_name' => $date->format('M Y'),
                        'total_pos' => $periodPos->count(),
                        'total_po_amount' => $totalAmount,
                        'paid_amount' => $paidAmount,
                        'outstanding_debt' => $outstandingAmount,
                        'total_quantity' => $totalQuantity,
                        'unique_suppliers' => $periodPos->pluck('supplier_id')->unique()->count(),
                        'unique_products' => $periodPos->pluck('product_id')->unique()->count(),
                        'payment_rate' => $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 1) : 0,
                        'credit_pos' => $periodPos->where('type_po', 'credit')->count(),
                        'cash_pos' => $periodPos->where('type_po', 'cash')->count(),
                    ]);
                }
            }
        } else {
            // Default 12 months
            for ($i = 11; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $monthKey = $month->format('Y-m');
                $monthPos = $monthlyGroups->get($monthKey, collect());

                $totalAmount = $monthPos->sum('total_amount');
                $paidAmount = $monthPos->filter(fn($po) => $po->status_paid === 'paid')->sum('total_amount');
                $outstandingAmount = $monthPos->filter(fn($po) => $po->status_paid === 'unpaid')->sum('total_amount');
                $totalQuantity = $monthPos->sum('quantity');

                $results->push((object)[
                    'period' => $monthKey,
                    'period_name' => $month->format('M Y'),
                    'total_pos' => $monthPos->count(),
                    'total_po_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'outstanding_debt' => $outstandingAmount,
                    'total_quantity' => $totalQuantity,
                    'unique_suppliers' => $monthPos->pluck('supplier_id')->unique()->count(),
                    'unique_products' => $monthPos->pluck('product_id')->unique()->count(),
                    'payment_rate' => $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 1) : 0,
                    'credit_pos' => $monthPos->where('type_po', 'credit')->count(),
                    'cash_pos' => $monthPos->where('type_po', 'cash')->count(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Get filtered statistics by supplier
     */
    public static function getFilteredSupplierStats($filters = [])
    {
        $query = static::with(['supplier'])->activeOnly();
        $query = static::applyFiltersToQuery($query, $filters);

        $allPos = $query->get();
        $supplierGroups = $allPos->groupBy('supplier_id');

        $results = collect();

        foreach ($supplierGroups as $supplierId => $supplierPos) {
            $supplier = $supplierPos->first()->supplier;

            $totalAmount = $supplierPos->sum('total_amount');
            $paidAmount = $supplierPos->filter(fn($po) => $po->status_paid === 'paid')->sum('total_amount');
            $outstandingDebt = $supplierPos->filter(fn($po) => $po->status_paid === 'unpaid')->sum('total_amount');
            $paymentRate = $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 1) : 0;

            $results->push((object)[
                'supplier_id' => $supplierId,
                'supplier_name' => $supplier->name ?? 'Unknown Supplier',
                'supplier_code' => $supplier->code ?? 'N/A',
                'total_pos' => $supplierPos->count(),
                'total_po_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'outstanding_debt' => $outstandingDebt,
                'payment_rate' => $paymentRate,
                'total_quantity' => $supplierPos->sum('quantity'),
                'unique_products' => $supplierPos->pluck('product_id')->unique()->count(),
                'average_po_amount' => round($supplierPos->avg('total_amount'), 2),
                'credit_pos' => $supplierPos->where('type_po', 'credit')->count(),
                'cash_pos' => $supplierPos->where('type_po', 'cash')->count(),
            ]);
        }

        return $results->sortByDesc('total_po_amount');
    }

    /**
     * Get filtered statistics by product
     */
    public static function getFilteredProductStats($filters = [])
    {
        $query = static::with(['product', 'product.category'])->activeOnly();
        $query = static::applyFiltersToQuery($query, $filters);

        $allPos = $query->get();
        $productGroups = $allPos->groupBy('product_id');

        $results = collect();

        foreach ($productGroups as $productId => $productPos) {
            $product = $productPos->first()->product;

            $totalAmount = $productPos->sum('total_amount');
            $paidAmount = $productPos->filter(fn($po) => $po->status_paid === 'paid')->sum('total_amount');
            $outstandingDebt = $productPos->filter(fn($po) => $po->status_paid === 'unpaid')->sum('total_amount');
            $paymentRate = $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 1) : 0;

            $results->push((object)[
                'product_id' => $productId,
                'product_name' => $product->name ?? 'Unknown Product',
                'product_code' => $product->code ?? 'N/A',
                'category_name' => $product->category->name ?? 'No Category',
                'total_pos' => $productPos->count(),
                'total_po_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'outstanding_debt' => $outstandingDebt,
                'payment_rate' => $paymentRate,
                'total_quantity' => $productPos->sum('quantity'),
                'unique_suppliers' => $productPos->pluck('supplier_id')->unique()->count(),
                'average_unit_price' => round($productPos->avg('unit_price'), 2),
                'average_po_amount' => round($productPos->avg('total_amount'), 2),
                'credit_pos' => $productPos->where('type_po', 'credit')->count(),
                'cash_pos' => $productPos->where('type_po', 'cash')->count(),
            ]);
        }

        return $results->sortByDesc('total_quantity');
    }

    /**
     * Get filtered statistics by status
     */
    public static function getFilteredStatsByStatus($filters = [])
    {
        $query = static::activeOnly();
        $query = static::applyFiltersToQuery($query, $filters);

        $allPos = $query->get();
        $statusGroups = $allPos->groupBy('status');

        $results = collect();
        foreach (['Requested', 'Processing', 'Shipped', 'Received', 'Done'] as $status) {
            $statusPos = $statusGroups->get($status, collect());

            if ($statusPos->count() > 0) {
                $paidPos = $statusPos->filter(fn($po) => $po->status_paid === 'paid');
                $unpaidPos = $statusPos->filter(fn($po) => $po->status_paid === 'unpaid');

                $totalAmount = $statusPos->sum('total_amount');
                $paidAmount = $paidPos->sum('total_amount');
                $outstandingAmount = $unpaidPos->sum('total_amount');

                $results->put($status, (object)[
                    'count' => $statusPos->count(),
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'outstanding_debt' => $outstandingAmount,
                    'total_quantity' => $statusPos->sum('quantity'),
                    'unique_suppliers' => $statusPos->pluck('supplier_id')->unique()->count(),
                    'unique_products' => $statusPos->pluck('product_id')->unique()->count(),
                    'payment_rate' => $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 1) : 0,
                ]);
            }
        }

        return $results;
    }

    /**
     * Get period label based on date filters
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
