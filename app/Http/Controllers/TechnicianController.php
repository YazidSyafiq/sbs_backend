<?php

namespace App\Http\Controllers;

use App\Models\Technician;
use App\Models\ServicePurchase;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TechnicianController extends Controller
{
    private function getCompanyInfo()
    {
        return [
            'name' => env('COMPANY_NAME', 'Your Company Name'),
            'address' => env('COMPANY_ADDRESS', 'Jl. Example Street No. 123'),
            'city' => env('COMPANY_CITY', 'Jakarta, Indonesia'),
            'phone' => env('COMPANY_PHONE', '+62 21 1234 5678'),
            'email' => env('COMPANY_EMAIL', 'info@yourcompany.com'),
            'website' => env('COMPANY_WEBSITE', 'www.yourcompany.com'),
        ];
    }

    public function report(Request $request)
    {
        // Get filter parameters
        $fromDate = $request->from_date ? Carbon::parse($request->from_date) : Carbon::now()->startOfMonth();
        $untilDate = $request->until_date ? Carbon::parse($request->until_date) : Carbon::now();
        $piutangStatus = $request->piutang_status ?? 'all';
        $technicianId = $request->technician_id;
        $minPrice = $request->min_price;
        $maxPrice = $request->max_price;

        // Query untuk mendapatkan Service Purchase dalam periode tertentu
        $servicePurchaseQuery = ServicePurchase::with(['items.technician', 'items.service', 'user.branch'])
            ->whereBetween('order_date', [$fromDate, $untilDate]);

        // Apply filters pada Service Purchase
        if ($piutangStatus === 'has_piutang') {
            $servicePurchaseQuery->where('status_paid', 'unpaid');
        } elseif ($piutangStatus === 'no_piutang') {
            $servicePurchaseQuery->where('status_paid', 'paid');
        }

        if ($technicianId) {
            $servicePurchaseQuery->whereHas('items.technician', function($q) use ($technicianId) {
                $q->where('id', $technicianId);
            });
        }

        $servicePurchases = $servicePurchaseQuery->orderBy('order_date', 'desc')->get();

        // Filter items berdasarkan technician criteria
        $filteredItems = collect();
        foreach ($servicePurchases as $purchase) {
            foreach ($purchase->items as $item) {
                if ($item->technician) {
                    // Apply specific technician filter
                    if ($technicianId && $item->technician->id != $technicianId) continue;

                    // Apply price filter pada technician
                    if ($minPrice && $item->technician->price < $minPrice) continue;
                    if ($maxPrice && $item->technician->price > $maxPrice) continue;

                    $filteredItems->push((object) [
                        'purchase' => $purchase,
                        'item' => $item,
                        'technician' => $item->technician,
                        'service' => $item->service,
                    ]);
                }
            }
        }

        // Group by technician untuk summary
        $technicianSummary = collect();
        if ($filteredItems->count() > 0) {
            $technicianSummary = $filteredItems->groupBy('technician.id')->map(function ($items, $technicianId) {
                $technician = $items->first()->technician;
                $totalItems = $items->count();
                $totalRevenue = $items->sum('item.selling_price');
                $totalCost = $items->sum('item.cost_price');

                // Group by purchase untuk menghitung yang paid/unpaid
                $purchases = $items->groupBy('purchase.id');
                $unpaidRevenue = 0;
                $paidRevenue = 0;
                $unpaidCount = 0;
                $paidCount = 0;

                foreach ($purchases as $purchaseItems) {
                    $purchase = $purchaseItems->first()->purchase;
                    $purchaseRevenue = $purchaseItems->sum('item.selling_price');

                    if ($purchase->status_paid === 'unpaid') {
                        $unpaidRevenue += $purchaseRevenue;
                        $unpaidCount += $purchaseItems->count();
                    } else {
                        $paidRevenue += $purchaseRevenue;
                        $paidCount += $purchaseItems->count();
                    }
                }

                return (object) [
                    'technician' => $technician,
                    'items' => $items,
                    'total_items' => $totalItems,
                    'total_revenue' => $totalRevenue,
                    'total_cost' => $totalCost,
                    'total_profit' => $totalRevenue - $totalCost,
                    'unpaid_revenue' => $unpaidRevenue,
                    'paid_revenue' => $paidRevenue,
                    'unpaid_count' => $unpaidCount,
                    'paid_count' => $paidCount,
                ];
            });
        }

        // Calculate overall totals
        $totalItems = $filteredItems->count();
        $totalRevenue = $filteredItems->sum('item.selling_price');
        $totalCost = $filteredItems->sum('item.cost_price');
        $totalProfit = $totalRevenue - $totalCost;

        // Calculate unpaid/paid totals
        $totalUnpaidRevenue = $technicianSummary->sum('unpaid_revenue');
        $totalPaidRevenue = $technicianSummary->sum('paid_revenue');
        $totalUnpaidCount = $technicianSummary->sum('unpaid_count');
        $totalTechniciansWithOrders = $technicianSummary->count();

        // Variables untuk compatibility dengan view
        $totalTechnicians = $totalTechniciansWithOrders;
        $totalPiutang = $totalUnpaidRevenue;
        $totalPoAmount = $totalRevenue;
        $averagePoAmount = $totalTechniciansWithOrders > 0 ? $totalRevenue / $totalTechniciansWithOrders : 0;

        // Calculate average price
        $averagePrice = 0;
        if ($technicianSummary->count() > 0) {
            $totalPrice = $technicianSummary->sum(function($summary) {
                return $summary->technician->price ?? 0;
            });
            $averagePrice = $totalPrice / $technicianSummary->count();
        }

        // Piutang summary
        $piutangSummary = [
            'has_piutang' => $technicianSummary->filter(function($summary) {
                return $summary->unpaid_revenue > 0;
            })->count(),
            'no_piutang' => $technicianSummary->filter(function($summary) {
                return $summary->unpaid_revenue <= 0;
            })->count(),
        ];

        // Get filter labels for display
        $filterLabels = $this->getFilterLabels($request);
        $companyInfo = $this->getCompanyInfo();

        return view('technician.report', compact(
            'filteredItems',
            'technicianSummary',
            'fromDate',
            'untilDate',
            'totalItems',
            'totalRevenue',
            'totalCost',
            'totalProfit',
            'totalUnpaidRevenue',
            'totalPaidRevenue',
            'totalUnpaidCount',
            'totalTechniciansWithOrders',
            'totalTechnicians',
            'totalPiutang',
            'totalPoAmount',
            'averagePrice',
            'averagePoAmount',
            'piutangSummary',
            'filterLabels',
            'companyInfo'
        ));
    }

    private function getFilterLabels(Request $request)
    {
        $labels = [];

        if ($request->piutang_status && $request->piutang_status !== 'all') {
            $labels['payment_status'] = $request->piutang_status === 'has_piutang' ? 'Unpaid Only' : 'Paid Only';
        }

        if ($request->technician_id) {
            $technician = Technician::find($request->technician_id);
            $labels['technician'] = $technician ? $technician->name . ' (' . $technician->code . ')' : 'Unknown Technician';
        }

        if ($request->min_price) {
            $labels['min_price'] = 'Min Fee: Rp ' . number_format($request->min_price, 0, ',', '.');
        }

        if ($request->max_price) {
            $labels['max_price'] = 'Max Fee: Rp ' . number_format($request->max_price, 0, ',', '.');
        }

        return $labels;
    }
}
