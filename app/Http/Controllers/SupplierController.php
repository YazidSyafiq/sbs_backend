<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\PurchaseProductSupplier;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SupplierController extends Controller
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
        $supplierId = $request->supplier_id;
        $minTotalPo = $request->min_total_po;
        $maxTotalPo = $request->max_total_po;

        // Query untuk mendapatkan PO dalam periode tertentu
        $purchaseOrderQuery = PurchaseProductSupplier::with(['supplier', 'product'])
            ->whereBetween('order_date', [$fromDate, $untilDate]);

        // Apply filters pada PO
        if ($piutangStatus === 'has_piutang') {
            $purchaseOrderQuery->where('status_paid', 'unpaid');
        } elseif ($piutangStatus === 'no_piutang') {
            $purchaseOrderQuery->where('status_paid', 'paid');
        }

        if ($supplierId) {
            $purchaseOrderQuery->where('supplier_id', $supplierId);
        }

        if ($minTotalPo) {
            $purchaseOrderQuery->where('total_amount', '>=', $minTotalPo);
        }

        if ($maxTotalPo) {
            $purchaseOrderQuery->where('total_amount', '<=', $maxTotalPo);
        }

        $purchaseOrders = $purchaseOrderQuery->orderBy('order_date', 'desc')->get();

        // Group by supplier untuk summary
        $supplierSummary = collect();
        if ($purchaseOrders->count() > 0) {
            $supplierSummary = $purchaseOrders->groupBy('supplier_id')->map(function ($orders, $supplierId) {
                $supplier = $orders->first()->supplier;
                $totalOrders = $orders->count();
                $totalAmount = $orders->sum('total_amount');
                $unpaidAmount = $orders->where('status_paid', 'unpaid')->sum('total_amount');
                $paidAmount = $orders->where('status_paid', 'paid')->sum('total_amount');
                $unpaidCount = $orders->where('status_paid', 'unpaid')->count();
                $paidCount = $orders->where('status_paid', 'paid')->count();

                return (object) [
                    'supplier' => $supplier,
                    'orders' => $orders,
                    'total_orders' => $totalOrders,
                    'total_amount' => $totalAmount,
                    'unpaid_amount' => $unpaidAmount,
                    'paid_amount' => $paidAmount,
                    'unpaid_count' => $unpaidCount,
                    'paid_count' => $paidCount,
                ];
            });
        }

        // Calculate overall totals
        $totalOrders = $purchaseOrders->count();
        $totalAmount = $purchaseOrders->sum('total_amount');
        $totalUnpaidAmount = $purchaseOrders->where('status_paid', 'unpaid')->sum('total_amount');
        $totalPaidAmount = $purchaseOrders->where('status_paid', 'paid')->sum('total_amount');
        $totalUnpaidCount = $purchaseOrders->where('status_paid', 'unpaid')->count();
        $totalSuppliersWithOrders = $supplierSummary->count();

        // Variables untuk compatibility dengan view
        $totalSuppliers = $totalSuppliersWithOrders;
        $totalPiutang = $totalUnpaidAmount;
        $totalPoAmount = $totalAmount;
        $averagePoAmount = $totalSuppliersWithOrders > 0 ? $totalAmount / $totalSuppliersWithOrders : 0;

        // Piutang summary
        $piutangSummary = [
            'has_piutang' => $supplierSummary->filter(function($summary) {
                return $summary->unpaid_amount > 0;
            })->count(),
            'no_piutang' => $supplierSummary->filter(function($summary) {
                return $summary->unpaid_amount <= 0;
            })->count(),
        ];

        // Get filter labels for display
        $filterLabels = $this->getFilterLabels($request);
        $companyInfo = $this->getCompanyInfo();

        return view('supplier.report', compact(
            'purchaseOrders',
            'supplierSummary',
            'fromDate',
            'untilDate',
            'totalOrders',
            'totalAmount',
            'totalUnpaidAmount',
            'totalPaidAmount',
            'totalUnpaidCount',
            'totalSuppliersWithOrders',
            'totalSuppliers',
            'totalPiutang',
            'totalPoAmount',
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

        if ($request->supplier_id) {
            $supplier = Supplier::find($request->supplier_id);
            $labels['supplier'] = $supplier ? $supplier->name . ' (' . $supplier->code . ')' : 'Unknown Supplier';
        }

        if ($request->min_total_po) {
            $labels['min_total_po'] = 'Min Amount: Rp ' . number_format($request->min_total_po, 0, ',', '.');
        }

        if ($request->max_total_po) {
            $labels['max_total_po'] = 'Max Amount: Rp ' . number_format($request->max_total_po, 0, ',', '.');
        }

        return $labels;
    }
}
