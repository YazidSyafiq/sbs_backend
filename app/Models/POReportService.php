<?php

namespace App\Models;

use App\Models\ServicePurchase;
use App\Models\ServicePurchaseItem;
use App\Models\Technician;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class POReportService extends Model
{
    // Gunakan table yang sama dengan ServicePurchase
    protected $table = 'service_purchases';

    // Cast untuk data types
    protected $casts = [
        'order_date' => 'date',
        'expected_proccess_date' => 'date',
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
        return $this->hasMany(ServicePurchaseItem::class, 'service_purchase_id');
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
     */
    public static function applyFiltersToQuery($query, $filters = [])
    {
        // Branch filter
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

        // Technician filter
        if (!empty($filters['technician_id'])) {
            $query->whereHas('items', function($q) use ($filters) {
                $q->where('technician_id', $filters['technician_id']);
            });
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
     * Get filtered profit analysis overview
     * ONLY calculate profit for PAID POs
     */
    public static function getFilteredProfitOverview($filters = [])
    {
        $query = static::with(['items.service'])->activeOnly();
        $query = static::applyFiltersToQuery($query, $filters);

        $allPos = $query->get();

        // ONLY include PAID POs for profit calculation
        $paidPos = $allPos->filter(fn($po) => $po->status_paid === 'paid');
        $paidItems = $paidPos->flatMap->items;

        $totalCost = $paidItems->sum('cost_price');
        $totalRevenue = $paidItems->sum('selling_price');
        $totalProfit = $totalRevenue - $totalCost;
        $profitMargin = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0;

        // Also calculate total outstanding for context
        $unpaidPos = $allPos->filter(fn($po) => $po->status_paid === 'unpaid');
        $unpaidItems = $unpaidPos->flatMap->items;
        $outstandingRevenue = $unpaidItems->sum('selling_price');
        $outstandingCost = $unpaidItems->sum('cost_price');
        $potentialProfit = $outstandingRevenue - $outstandingCost;

        return (object)[
            'total_cost' => $totalCost,
            'total_revenue' => $totalRevenue,
            'total_profit' => $totalProfit,
            'profit_margin' => $profitMargin,
            'total_services' => $paidItems->count(),
            'total_orders' => $paidPos->count(),
            'outstanding_revenue' => $outstandingRevenue,
            'outstanding_cost' => $outstandingCost,
            'potential_profit' => $potentialProfit,
            'unpaid_orders' => $unpaidPos->count(),
        ];
    }

    /**
     * Get top profitable services
     * ONLY calculate profit for PAID POs
     */
    public static function getTopProfitableServices($filters = [], $limit = 10)
    {
        $query = static::with(['items.service'])->activeOnly();
        $query = static::applyFiltersToQuery($query, $filters);

        $allPos = $query->get();

        // ONLY include PAID POs for profit calculation
        $paidPos = $allPos->filter(fn($po) => $po->status_paid === 'paid');
        $paidItems = $paidPos->flatMap->items;

        $serviceStats = $paidItems->groupBy('service_id')->map(function($items) {
            $service = $items->first()->service;
            $totalQuantity = $items->count();
            $totalCost = $items->sum('cost_price');
            $totalRevenue = $items->sum('selling_price');
            $totalProfit = $totalRevenue - $totalCost;
            $profitMargin = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0;

            return (object)[
                'service_id' => $service->id,
                'service_name' => $service->name,
                'service_code' => $service->code,
                'total_quantity' => $totalQuantity,
                'total_cost' => $totalCost,
                'total_revenue' => $totalRevenue,
                'total_profit' => $totalProfit,
                'profit_margin' => $profitMargin,
                'avg_cost_per_service' => $totalQuantity > 0 ? round($totalCost / $totalQuantity, 2) : 0,
                'avg_revenue_per_service' => $totalQuantity > 0 ? round($totalRevenue / $totalQuantity, 2) : 0,
                'avg_profit_per_service' => $totalQuantity > 0 ? round($totalProfit / $totalQuantity, 2) : 0,
            ];
        })->sortByDesc('total_profit')->take($limit);

        return $serviceStats;
    }

    /**
     * Get profit trends by period
     * ONLY calculate profit for PAID POs
     */
    public static function getFilteredProfitTrends($filters = [])
    {
        $monthlyData = static::getFilteredMonthlyTrends($filters);

        return $monthlyData->map(function($trend) use ($filters) {
            // Get POs for this period
            $periodFilters = $filters;
            if (strlen($trend->period) === 7) { // Y-m format
                $periodStart = Carbon::createFromFormat('Y-m', $trend->period)->startOfMonth();
                $periodEnd = Carbon::createFromFormat('Y-m', $trend->period)->endOfMonth();
            } else { // Y-m-d format
                $periodStart = Carbon::parse($trend->period)->startOfDay();
                $periodEnd = Carbon::parse($trend->period)->endOfDay();
            }

            $periodFilters['date_from'] = $periodStart->toDateString();
            $periodFilters['date_until'] = $periodEnd->toDateString();

            $query = static::with(['items.service'])->activeOnly();
            $query = static::applyFiltersToQuery($query, $periodFilters);

            $periodPos = $query->get();

            // ONLY include PAID POs for profit calculation
            $paidPeriodPos = $periodPos->filter(fn($po) => $po->status_paid === 'paid');
            $paidPeriodItems = $paidPeriodPos->flatMap->items;

            $periodCost = $paidPeriodItems->sum('cost_price');
            $periodRevenue = $paidPeriodItems->sum('selling_price');
            $periodProfit = $periodRevenue - $periodCost;
            $periodMargin = $periodRevenue > 0 ? round(($periodProfit / $periodRevenue) * 100, 2) : 0;

            $trend->total_cost = $periodCost;
            $trend->total_revenue = $periodRevenue;
            $trend->total_profit = $periodProfit;
            $trend->profit_margin = $periodMargin;
            $trend->paid_pos = $paidPeriodPos->count();

            return $trend;
        });
    }

    /**
     * Get cost vs revenue breakdown by branch
     * ONLY calculate profit for PAID POs
     */
    public static function getFilteredProfitByBranch($filters = [])
    {
        $query = ServicePurchase::with(['user.branch', 'items.service'])
            ->whereNotIn('status', ['Draft', 'Cancelled']);

        $query = static::applyFiltersToQuery($query, $filters);

        $allPos = $query->get();

        $branchGroups = $allPos->groupBy(function($po) {
            return $po->user->branch->name ?? 'No Branch';
        });

        $results = collect();

        foreach ($branchGroups as $branchName => $branchPos) {
            $branchInfo = $branchPos->first()->user->branch ?? null;

            // ONLY include PAID POs for profit calculation
            $paidBranchPos = $branchPos->filter(fn($po) => $po->status_paid === 'paid');
            $paidBranchItems = $paidBranchPos->flatMap->items;

            $totalCost = $paidBranchItems->sum('cost_price');
            $totalRevenue = $paidBranchItems->sum('selling_price');
            $totalProfit = $totalRevenue - $totalCost;
            $profitMargin = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0;

            // Also track unpaid for context
            $unpaidBranchPos = $branchPos->filter(fn($po) => $po->status_paid === 'unpaid');
            $unpaidBranchItems = $unpaidBranchPos->flatMap->items;
            $potentialRevenue = $unpaidBranchItems->sum('selling_price');
            $potentialCost = $unpaidBranchItems->sum('cost_price');

            $results->push((object)[
                'branch_id' => $branchInfo->id ?? null,
                'branch_name' => $branchName,
                'branch_code' => $branchInfo->code ?? 'N/A',
                'total_cost' => $totalCost,
                'total_revenue' => $totalRevenue,
                'total_profit' => $totalProfit,
                'profit_margin' => $profitMargin,
                'total_services' => $paidBranchItems->count(),
                'total_pos' => $branchPos->count(),
                'paid_pos' => $paidBranchPos->count(),
                'unpaid_pos' => $unpaidBranchPos->count(),
                'potential_revenue' => $potentialRevenue,
                'potential_profit' => $potentialRevenue - $potentialCost,
            ]);
        }

        return $results->sortByDesc('total_profit');
    }

    /**
     * Get technician analysis - KHUSUS UNTUK PUSAT
     * Untuk mengetahui hutang ke technician, performa, dll
     */
    public static function getFilteredTechnicianAnalysis($filters = [])
    {
        $query = static::with(['items.technician', 'items.service'])->activeOnly();
        $query = static::applyFiltersToQuery($query, $filters);

        $allPos = $query->get();

        // Group by technician
        $technicianGroups = $allPos->flatMap->items->groupBy('technician_id');

        $results = collect();

        foreach ($technicianGroups as $technicianId => $items) {
            $technician = $items->first()->technician;
            if (!$technician) continue;

            // Hitung total PO value untuk technician ini (dari selling price)
            $totalPoValue = $items->sum('selling_price');

            // Hitung total cost yang harus dibayar ke technician
            $totalCost = $items->sum('cost_price');

            // Hitung berapa banyak PO yang sudah paid vs unpaid
            $paidItems = $items->filter(function($item) {
                return $item->servicePurchase->status_paid === 'paid';
            });

            $unpaidItems = $items->filter(function($item) {
                return $item->servicePurchase->status_paid === 'unpaid';
            });

            $paidCost = $paidItems->sum('cost_price');
            $paidRevenue = $paidItems->sum('selling_price');
            $unpaidCost = $unpaidItems->sum('cost_price');
            $unpaidRevenue = $unpaidItems->sum('selling_price');

            // Hitung hutang aktual (berdasarkan status credit/cash dan paid/unpaid)
            $actualDebt = 0;
            foreach ($items as $item) {
                $po = $item->servicePurchase;
                if ($po->type_po === 'credit' && $po->status_paid === 'unpaid') {
                    $actualDebt += $item->cost_price;
                }
            }

            $totalServices = $items->count();
            $completedServices = $items->filter(function($item) {
                return $item->servicePurchase->status === 'Done';
            })->count();

            // Calculate profit from paid items only
            $realizedProfit = $paidRevenue - $paidCost;
            $potentialProfit = $unpaidRevenue - $unpaidCost;

            $results->push((object)[
                'technician_id' => $technician->id,
                'technician_name' => $technician->name,
                'technician_code' => $technician->code,
                'total_services' => $totalServices,
                'completed_services' => $completedServices,
                'completion_rate' => $totalServices > 0 ? round(($completedServices / $totalServices) * 100, 1) : 0,
                'total_po_value' => $totalPoValue,
                'total_cost_owed' => $totalCost,
                'paid_cost' => $paidCost,
                'paid_revenue' => $paidRevenue,
                'unpaid_cost' => $unpaidCost,
                'unpaid_revenue' => $unpaidRevenue,
                'actual_debt' => $actualDebt, // Hutang yang benar-benar harus dibayar
                'realized_profit' => $realizedProfit, // Profit dari PO yang sudah paid
                'potential_profit' => $potentialProfit, // Profit dari PO yang belum paid
                'profit_margin' => $paidRevenue > 0 ? round(($realizedProfit / $paidRevenue) * 100, 2) : 0,
                'current_piutang' => $technician->piutang, // Dari database
                'total_po_recorded' => $technician->total_po, // Dari database
                'average_service_value' => $totalServices > 0 ? round($totalPoValue / $totalServices, 2) : 0,
                'average_cost_per_service' => $totalServices > 0 ? round($totalCost / $totalServices, 2) : 0,
                'paid_services' => $paidItems->count(),
                'unpaid_services' => $unpaidItems->count(),
            ]);
        }

        return $results->sortByDesc('actual_debt');
    }

    /**
     * Get technician debt overview
     */
    public static function getTechnicianDebtOverview($filters = [])
    {
        $technicianAnalysis = static::getFilteredTechnicianAnalysis($filters);

        $totalDebt = $technicianAnalysis->sum('actual_debt');
        $totalTechnicians = $technicianAnalysis->count();
        $techniciansWithDebt = $technicianAnalysis->filter(fn($t) => $t->actual_debt > 0)->count();
        $averageDebtPerTechnician = $totalTechnicians > 0 ? $totalDebt / $totalTechnicians : 0;
        $totalUnpaidCost = $technicianAnalysis->sum('unpaid_cost');
        $totalPaidCost = $technicianAnalysis->sum('paid_cost');
        $totalServices = $technicianAnalysis->sum('total_services');
        $totalCompletedServices = $technicianAnalysis->sum('completed_services');
        $totalRealizedProfit = $technicianAnalysis->sum('realized_profit');
        $totalPotentialProfit = $technicianAnalysis->sum('potential_profit');

        return (object)[
            'total_debt_to_technicians' => $totalDebt,
            'total_technicians' => $totalTechnicians,
            'technicians_with_debt' => $techniciansWithDebt,
            'average_debt_per_technician' => $averageDebtPerTechnician,
            'total_unpaid_cost' => $totalUnpaidCost,
            'total_paid_cost' => $totalPaidCost,
            'debt_percentage' => $totalUnpaidCost + $totalPaidCost > 0 ? round(($totalDebt / ($totalUnpaidCost + $totalPaidCost)) * 100, 1) : 0,
            'total_services' => $totalServices,
            'completed_services' => $totalCompletedServices,
            'completion_rate' => $totalServices > 0 ? round(($totalCompletedServices / $totalServices) * 100, 1) : 0,
            'total_realized_profit' => $totalRealizedProfit,
            'total_potential_profit' => $totalPotentialProfit,
            'profit_realization_rate' => $totalRealizedProfit + $totalPotentialProfit > 0 ? round(($totalRealizedProfit / ($totalRealizedProfit + $totalPotentialProfit)) * 100, 1) : 0,
        ];
    }

    /**
     * Get filtered overview statistics with breakdown
     */
    public static function getFilteredOverviewStats($filters = [])
    {
        $query = static::with(['user.branch'])->activeOnly();
        $query = static::applyFiltersToQuery($query, $filters);

        $allPos = $query->get();

        $paidPos = $allPos->filter(fn($po) => $po->status_paid === 'paid');
        $unpaidPos = $allPos->filter(fn($po) => $po->status_paid === 'unpaid');

        $totalPosAmount = $allPos->sum('total_amount');
        $paidAmount = $paidPos->sum('total_amount');
        $outstandingDebt = $unpaidPos->sum('total_amount');

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
            'total_po_amount' => $totalPosAmount,
            'paid_amount' => $paidAmount,
            'outstanding_debt' => $outstandingDebt,
            'payment_rate' => $paymentRate,
            'credit_count' => $creditPos->count(),
            'cash_count' => $cashPos->count(),
            'credit_total_amount' => $creditTotalAmount,
            'credit_paid_amount' => $creditPaidAmount,
            'credit_outstanding' => $creditOutstanding,
            'credit_payment_rate' => $creditPaymentRate,
            'cash_total_amount' => $cashTotalAmount,
            'cash_paid_amount' => $cashPaidAmount,
            'cash_outstanding' => $cashOutstanding,
            'cash_payment_rate' => $cashPaymentRate,
        ];
    }

    /**
     * Get filtered monthly trends with dynamic date range and breakdown
     */
    public static function getFilteredMonthlyTrends($filters = [])
    {
        if (!empty($filters['date_from']) || !empty($filters['date_until'])) {
            $startDate = !empty($filters['date_from']) ? Carbon::parse($filters['date_from']) : Carbon::now()->subYear();
            $endDate = !empty($filters['date_until']) ? Carbon::parse($filters['date_until']) : Carbon::now();

            $query = ServicePurchase::query()
                ->whereNotIn('status', ['Draft', 'Cancelled'])
                ->whereDate('order_date', '>=', $startDate)
                ->whereDate('order_date', '<=', $endDate);
        } else {
            $startDate = Carbon::now()->subMonths(11)->startOfMonth();
            $endDate = Carbon::now();

            $query = ServicePurchase::query()
                ->whereNotIn('status', ['Draft', 'Cancelled'])
                ->where('order_date', '>=', $startDate);
        }

        $filtersWithoutDate = $filters;
        unset($filtersWithoutDate['date_from'], $filtersWithoutDate['date_until']);
        $query = static::applyFiltersToQuery($query, $filtersWithoutDate);

        $allPos = $query->get();

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

        if (!empty($filters['date_from']) || !empty($filters['date_until'])) {
            $diffInDays = $startDate->diffInDays($endDate);

            if ($diffInDays <= 31) {
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
                        'total_amount' => $totalAmount,
                    ]);
                }
            } elseif ($diffInDays <= 365) {
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
                        'total_amount' => $totalAmount,
                    ]);
                }
            } else {
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
                        'total_amount' => $totalAmount,
                    ]);
                }
            }
        } else {
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
        foreach (['Requested', 'Approved', 'In Progress', 'Done'] as $status) {
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
     */
    public static function getFilteredAccountingSummaryByBranch($filters = [])
    {
        $query = ServicePurchase::with(['user.branch'])->whereNotIn('status', ['Draft', 'Cancelled']);
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

    /**
     * Get filter summary for display
     */
    public static function getFilterSummary($filters = [])
    {
        $summary = [];

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

        if (!empty($filters['technician_id'])) {
            $technicianName = Technician::find($filters['technician_id'])->name ?? 'Unknown Technician';
            $summary[] = "Technician: {$technicianName}";
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
}
