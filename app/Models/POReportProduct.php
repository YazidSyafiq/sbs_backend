<?php

namespace App\Models;

use App\Models\PurchaseProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class POReportProduct extends Model
{
     // Gunakan table yang sama dengan PurchaseProduct
    protected $table = 'purchase_products';

    // Cast untuk data types
    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
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
     * Relasi ke Items
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseProductItem::class, 'purchase_product_id');
    }

    /**
     * Scope untuk filter by branch
     */
    public function scopeByBranch($query, $branchId)
    {
        return $query->whereHas('user', function ($q) use ($branchId) {
            $q->where('branch_id', $branchId);
        });
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
     * UPDATED: branch_ids menjadi branch_id (single)
     */
    public static function applyFiltersToQuery($query, $filters = [])
    {
        // Branch filter - CHANGED: from branch_ids (array) to branch_id (single)
        if (!empty($filters['branch_id'])) {
            $query->whereHas('user', function($q) use ($filters) {
                $q->where('branch_id', $filters['branch_id']);
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

        // User role filter (apply at the end)
        $user = auth()->user();
        if ($user && $user->hasRole('User')) {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('branch_id', $user->branch_id);
            });
        }

        return $query;
    }

    /**
     * Get filter summary for display
     * UPDATED: branch_ids menjadi branch_id (single)
     */
    public static function getFilterSummary($filters = [])
    {
        $summary = [];

        // CHANGED: from branch_ids to branch_id
        if (!empty($filters['branch_id'])) {
            $branchName = Branch::find($filters['branch_id'])->name ?? 'Unknown Branch';
            $summary[] = "Branch: {$branchName}";
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
            $summary[] = "Outstanding debts only";
        }

        return $summary;
    }

    /**
     * Get filtered overview statistics with breakdown
     * Menampilkan total PO value dengan breakdown paid/unpaid
     */
    public static function getFilteredOverviewStats($filters = [])
    {
        $query = static::with(['user.branch'])->activeOnly();

        // Apply filters
        $query = static::applyFiltersToQuery($query, $filters);

        $allPos = $query->get();

        $paidPos = $allPos->filter(fn($po) => $po->status_paid === 'paid');
        $unpaidPos = $allPos->filter(fn($po) => $po->status_paid === 'unpaid');

        $totalPosAmount = $allPos->sum('total_amount'); // Total semua PO
        $paidAmount = $paidPos->sum('total_amount');
        $outstandingDebt = $unpaidPos->sum('total_amount');

        // Calculate payment rate
        $paymentRate = $totalPosAmount > 0 ? round(($paidAmount / $totalPosAmount) * 100, 1) : 0;

        // Credit statistics
        $creditPos = $allPos->where('type_po', 'credit');
        $creditPaid = $creditPos->filter(fn($po) => $po->status_paid === 'paid');
        $creditUnpaid = $creditPos->filter(fn($po) => $po->status_paid === 'unpaid');
        $creditTotalAmount = $creditPos->sum('total_amount');
        $creditPaidAmount = $creditPaid->sum('total_amount');
        $creditOutstanding = $creditUnpaid->sum('total_amount');
        $creditPaymentRate = $creditTotalAmount > 0 ? round(($creditPaidAmount / $creditTotalAmount) * 100, 1) : 0;

        // Cash statistics
        $cashPos = $allPos->where('type_po', 'cash');
        $cashPaid = $cashPos->filter(fn($po) => $po->status_paid === 'paid');
        $cashUnpaid = $cashPos->filter(fn($po) => $po->status_paid === 'unpaid');
        $cashTotalAmount = $cashPos->sum('total_amount');
        $cashPaidAmount = $cashPaid->sum('total_amount');
        $cashOutstanding = $cashUnpaid->sum('total_amount');
        $cashPaymentRate = $cashTotalAmount > 0 ? round(($cashPaidAmount / $cashTotalAmount) * 100, 1) : 0;

        return (object)[
            'total_count' => $allPos->count(),
            'total_po_amount' => $totalPosAmount, // Total semua PO
            'paid_amount' => $paidAmount,
            'outstanding_debt' => $outstandingDebt,
            'payment_rate' => $paymentRate,

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
     * Get filtered monthly trends with dynamic date range and breakdown
     * Dinamis berdasarkan filter date dan menampilkan breakdown
     */
    public static function getFilteredMonthlyTrends($filters = [])
    {
        // Determine date range based on filters
        if (!empty($filters['date_from']) || !empty($filters['date_until'])) {
            // Use filter dates
            $startDate = !empty($filters['date_from']) ? Carbon::parse($filters['date_from']) : Carbon::now()->subYear();
            $endDate = !empty($filters['date_until']) ? Carbon::parse($filters['date_until']) : Carbon::now();

            $query = PurchaseProduct::query()
                ->whereNotIn('status', ['Draft', 'Cancelled'])
                ->whereDate('order_date', '>=', $startDate)
                ->whereDate('order_date', '<=', $endDate);
        } else {
            // Default: last 12 months
            $startDate = Carbon::now()->subMonths(11)->startOfMonth();
            $endDate = Carbon::now();

            $query = PurchaseProduct::query()
                ->whereNotIn('status', ['Draft', 'Cancelled'])
                ->where('order_date', '>=', $startDate);
        }

        // Apply other filters (except date filters since we handled them above)
        $filtersWithoutDate = $filters;
        unset($filtersWithoutDate['date_from'], $filtersWithoutDate['date_until']);
        $query = static::applyFiltersToQuery($query, $filtersWithoutDate);

        $allPos = $query->get();

        // Group by appropriate period
        if (!empty($filters['date_from']) || !empty($filters['date_until'])) {
            $diffInDays = $startDate->diffInDays($endDate);

            if ($diffInDays <= 31) {
                // Daily grouping for periods <= 1 month
                $groupBy = 'Y-m-d';
                $format = 'd M';
            } elseif ($diffInDays <= 365) {
                // Weekly grouping for periods <= 1 year
                $groupBy = function($po) {
                    return $po->order_date->startOfWeek()->format('Y-m-d');
                };
                $format = 'W M Y';
            } else {
                // Monthly grouping for periods > 1 year
                $groupBy = 'Y-m';
                $format = 'M Y';
            }
        } else {
            // Default monthly grouping
            $groupBy = 'Y-m';
            $format = 'M Y';
        }

        if (is_string($groupBy)) {
            $monthlyGroups = $allPos->groupBy(function($po) use ($groupBy) {
                return $po->order_date->format($groupBy);
            });
        } else {
            $monthlyGroups = $allPos->groupBy($groupBy);
        }

        $results = collect();

        // Generate periods dengan breakdown
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

                    $results->push((object)[
                        'period' => $key,
                        'period_name' => $date->format('d M'),
                        'month' => $key,
                        'month_name' => $date->format('d M'),
                        'total_pos' => $periodPos->count(),
                        'total_po_amount' => $totalAmount,
                        'paid_amount' => $paidAmount,
                        'outstanding_debt' => $outstandingAmount,
                        'payment_rate' => $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 1) : 0,
                        'credit_pos' => $periodPos->where('type_po', 'credit')->count(),
                        'cash_pos' => $periodPos->where('type_po', 'cash')->count(),
                        // Legacy compatibility
                        'total_amount' => $totalAmount,
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

                    $results->push((object)[
                        'period' => $key,
                        'period_name' => 'W' . $date->weekOfYear . ' ' . $date->format('M Y'),
                        'month' => $key,
                        'month_name' => 'W' . $date->weekOfYear . ' ' . $date->format('M Y'),
                        'total_pos' => $periodPos->count(),
                        'total_po_amount' => $totalAmount,
                        'paid_amount' => $paidAmount,
                        'outstanding_debt' => $outstandingAmount,
                        'payment_rate' => $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 1) : 0,
                        'credit_pos' => $periodPos->where('type_po', 'credit')->count(),
                        'cash_pos' => $periodPos->where('type_po', 'cash')->count(),
                        // Legacy compatibility
                        'total_amount' => $totalAmount,
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

                    $results->push((object)[
                        'period' => $key,
                        'period_name' => $date->format('M Y'),
                        'month' => $key,
                        'month_name' => $date->format('M Y'),
                        'total_pos' => $periodPos->count(),
                        'total_po_amount' => $totalAmount,
                        'paid_amount' => $paidAmount,
                        'outstanding_debt' => $outstandingAmount,
                        'payment_rate' => $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 1) : 0,
                        'credit_pos' => $periodPos->where('type_po', 'credit')->count(),
                        'cash_pos' => $periodPos->where('type_po', 'cash')->count(),
                        // Legacy compatibility
                        'total_amount' => $totalAmount,
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

                $results->push((object)[
                    'period' => $monthKey,
                    'period_name' => $month->format('M Y'),
                    'month' => $monthKey,
                    'month_name' => $month->format('M Y'),
                    'total_pos' => $monthPos->count(),
                    'total_po_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'outstanding_debt' => $outstandingAmount,
                    'payment_rate' => $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 1) : 0,
                    'credit_pos' => $monthPos->where('type_po', 'credit')->count(),
                    'cash_pos' => $monthPos->where('type_po', 'cash')->count(),
                    // Legacy compatibility
                    'total_amount' => $totalAmount,
                ]);
            }
        }

        return $results;
    }

    /**
     * Get filtered statistics by type with breakdown
     */
    public static function getFilteredStatsByType($filters = [])
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
                    'payment_rate' => $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 1) : 0,
                ]);
            }
        }

        return $results;
    }

    /**
     * Get filtered branch summary with breakdown
     * Menampilkan total PO dengan breakdown paid/unpaid per branch
     */
    public static function getFilteredAccountingSummaryByBranch($filters = [])
    {
        $query = PurchaseProduct::with(['user.branch'])->whereNotIn('status', ['Draft', 'Cancelled']);

        $query = static::applyFiltersToQuery($query, $filters);

        $allPos = $query->get();

        $branchGroups = $allPos->groupBy(function($po) {
            return $po->user->branch->name ?? 'No Branch';
        });

        $results = collect();

        foreach ($branchGroups as $branchName => $branchPos) {
            $branchInfo = $branchPos->first()->user->branch ?? null;

            $totalAmount = $branchPos->sum('total_amount');
            $paidAmount = $branchPos->filter(fn($po) => $po->status_paid === 'paid')->sum('total_amount');
            $outstandingDebt = $branchPos->filter(fn($po) => $po->status_paid === 'unpaid')->sum('total_amount');
            $paymentRate = $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 1) : 0;

            $results->push((object)[
                'branch_id' => $branchInfo->id ?? null,
                'branch_name' => $branchName,
                'branch_code' => $branchInfo->code ?? 'N/A',
                'total_pos' => $branchPos->count(),
                'total_po_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'outstanding_debt' => $outstandingDebt,
                'payment_rate' => $paymentRate,
                'average_po_amount' => round($branchPos->avg('total_amount'), 2),
                // Legacy compatibility
                'total_amount' => $totalAmount,
            ]);
        }

        return $results->sortByDesc('total_po_amount');
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

    // ===============================
    // ORIGINAL METHODS (For backward compatibility)
    // ===============================

    /**
     * Get accounting summary by branch - ORIGINAL VERSION (for compatibility)
     */
    public static function getAccountingSummaryByBranch()
    {
        $allPos = PurchaseProduct::with(['user.branch'])
            ->whereNotIn('status', ['Draft', 'Cancelled'])
            ->get();

        $branchGroups = $allPos->groupBy(function($po) {
            return $po->user->branch->name ?? 'No Branch';
        });

        $results = collect();

        foreach ($branchGroups as $branchName => $branchPos) {
            $branchInfo = $branchPos->first()->user->branch ?? null;

            $results->push((object)[
                'branch_id' => $branchInfo->id ?? null,
                'branch_name' => $branchName,
                'branch_code' => $branchInfo->code ?? 'N/A',
                'total_pos' => $branchPos->count(),
                'total_amount' => $branchPos->sum('total_amount'),
                'outstanding_debt' => $branchPos->filter(fn($po) => $po->status_paid === 'unpaid')->sum('total_amount'),
                'paid_amount' => $branchPos->filter(fn($po) => $po->status_paid === 'paid')->sum('total_amount'),
                'average_po_amount' => round($branchPos->avg('total_amount'), 2),
            ]);
        }

        return $results->sortByDesc('total_amount');
    }

    /**
     * Get monthly trends - ORIGINAL VERSION (for compatibility)
     */
    public static function getMonthlyTrends($months = 12, $userBranchId = null)
    {
        $query = PurchaseProduct::query()
            ->whereNotIn('status', ['Draft', 'Cancelled'])
            ->where('order_date', '>=', now()->subMonths($months - 1)->startOfMonth());

        // Filter berdasarkan user role jika diperlukan
        if ($userBranchId) {
            $query->whereHas('user', function($q) use ($userBranchId) {
                $q->where('branch_id', $userBranchId);
            });
        }

        $allPos = $query->get();

        $monthlyGroups = $allPos->groupBy(function($po) {
            return $po->order_date->format('Y-m');
        });

        $results = collect();

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthKey = $month->format('Y-m');
            $monthPos = $monthlyGroups->get($monthKey, collect());

            $results->push((object)[
                'month' => $monthKey,
                'month_name' => $month->format('M Y'),
                'total_pos' => $monthPos->count(),
                'total_amount' => $monthPos->sum('total_amount'),
                'outstanding_debt' => $monthPos->filter(fn($po) => $po->status_paid === 'unpaid')->sum('total_amount'),
                'credit_pos' => $monthPos->where('type_po', 'credit')->count(),
                'cash_pos' => $monthPos->where('type_po', 'cash')->count(),
            ]);
        }

        return $results;
    }

    /**
     * Get overview statistics - ORIGINAL VERSION (for compatibility)
     */
    public static function getOverviewStats()
    {
        $allPos = static::with(['user.branch'])
            ->activeOnly()
            ->get();

        return (object)[
            'total_count' => $allPos->count(),
            'total_amount' => $allPos->sum('total_amount'),
            'outstanding_debt' => $allPos->filter(fn($po) => $po->status_paid === 'unpaid')->sum('total_amount'),
            'paid_amount' => $allPos->filter(fn($po) => $po->status_paid === 'paid')->sum('total_amount'),
            'credit_count' => $allPos->where('type_po', 'credit')->count(),
            'cash_count' => $allPos->where('type_po', 'cash')->count(),
            'credit_amount' => $allPos->where('type_po', 'credit')->sum('total_amount'),
            'cash_amount' => $allPos->where('type_po', 'cash')->sum('total_amount'),
            'credit_outstanding' => $allPos->filter(fn($po) => $po->type_po === 'credit' && $po->status_paid === 'unpaid')->sum('total_amount'),
            'cash_outstanding' => $allPos->filter(fn($po) => $po->type_po === 'cash' && $po->status_paid === 'unpaid')->sum('total_amount'),
        ];
    }

    /**
     * Get statistics by type - ORIGINAL VERSION (for compatibility)
     */
    public static function getStatsByType()
    {
        $allPos = static::activeOnly()->get();

        $statusGroups = $allPos->groupBy('status');

        $results = collect();
        foreach (['Requested', 'Processing', 'Shipped', 'Received', 'Done'] as $status) {
            $statusPos = $statusGroups->get($status, collect());

            if ($statusPos->count() > 0) {
                $results->put($status, (object)[
                    'count' => $statusPos->count(),
                    'total_amount' => $statusPos->sum('total_amount'),
                ]);
            }
        }

        return $results;
    }
}
