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

        // Query untuk mendapatkan POs dengan items
        $purchaseOrdersQuery = PurchaseProductSupplier::with(['supplier', 'items.product'])
            ->whereBetween('order_date', [$fromDate, $untilDate])
            ->whereNotIn('status', ['Cancelled'])
            ->whereNull('deleted_at');

        // Apply filters
        if ($piutangStatus === 'has_piutang') {
            $purchaseOrdersQuery->where('status_paid', 'unpaid');
        } elseif ($piutangStatus === 'no_piutang') {
            $purchaseOrdersQuery->where('status_paid', 'paid');
        }

        if ($supplierId) {
            $purchaseOrdersQuery->where('supplier_id', $supplierId);
        }

        if ($minTotalPo) {
            $purchaseOrdersQuery->where('total_amount', '>=', $minTotalPo);
        }

        if ($maxTotalPo) {
            $purchaseOrdersQuery->where('total_amount', '<=', $maxTotalPo);
        }

        $purchaseOrders = $purchaseOrdersQuery->orderBy('order_date', 'desc')->get();

        // Format data untuk view
        $purchaseOrders = $purchaseOrders->map(function($po) {
            // Get all products in this PO
            $products = $po->items->filter(function($item) {
                return $item->product && $item->product->deleted_at === null;
            })->map(function($item) {
                return [
                    'name' => $item->product->name,
                    'code' => $item->product->code,
                    'quantity' => $item->quantity,
                    'unit' => $item->product->unit ?? 'pcs',
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                ];
            });

            return (object)[
                'po_id' => $po->id,
                'po_number' => $po->po_number,
                'order_date' => $po->order_date,
                'status_paid' => $po->status_paid,
                'supplier' => $po->supplier,
                'products' => $products,
                'total_items' => $products->count(),
                'total_quantity' => $products->sum('quantity'),
                'total_amount' => $po->total_amount,
            ];
        });

        // Calculate statistics
        $uniqueSuppliers = $purchaseOrders->pluck('supplier.id')->unique()->count();
        $totalPiutang = $purchaseOrders->where('status_paid', 'unpaid')->sum('total_amount');
        $totalPoAmount = $purchaseOrders->sum('total_amount');
        $averagePoAmount = $uniqueSuppliers > 0 ? $totalPoAmount / $uniqueSuppliers : 0;
        $totalSuppliers = $uniqueSuppliers;

        // Get filter labels for display
        $filterLabels = $this->getFilterLabels($request);
        $companyInfo = $this->getCompanyInfo();

        return view('supplier.report', compact(
            'purchaseOrders',
            'fromDate',
            'untilDate',
            'totalSuppliers',
            'totalPiutang',
            'totalPoAmount',
            'averagePoAmount',
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
