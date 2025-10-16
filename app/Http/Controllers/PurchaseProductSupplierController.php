<?php

namespace App\Http\Controllers;

use App\Models\PurchaseProductSupplier;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PurchaseProductSupplierController extends Controller
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

    /**
     * Detect if request is from mobile/Flutter app
     */
    private function isMobileAccess(Request $request)
    {
        // Method 1: Check for query parameter
        if ($request->has('mobile') || $request->has('flutter')) {
            return true;
        }

        return false;
    }

    public function faktur(Request $request, PurchaseProductSupplier $purchaseProduct)
    {
        $purchaseProduct->load([]);
        $companyInfo = $this->getCompanyInfo();
        $isFromMobile = $this->isMobileAccess($request);

        return view('purchase-product-supplier.faktur', compact('purchaseProduct', 'companyInfo', 'isFromMobile'));
    }

    public function report(Request $request)
    {
        // Get filter parameters
        $fromDate = $request->from_date ? Carbon::parse($request->from_date) : Carbon::now()->startOfMonth();
        $untilDate = $request->until_date ? Carbon::parse($request->until_date) : Carbon::now();
        $supplierId = $request->supplier_id;
        $productId = $request->product_id;
        $typePo = $request->type_po;
        $statusPaid = $request->status_paid;
        $status = $request->status;

        // Build query for PurchaseProductSupplier
        $query = PurchaseProductSupplier::with(['user.branch', 'product', 'supplier'])
            ->whereBetween('order_date', [$fromDate, $untilDate]);

        // Apply filters
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($productId) {
            $query->where('product_id', $productId);
        }

        if ($typePo) {
            $query->where('type_po', $typePo);
        }

        if ($statusPaid) {
            $query->where('status_paid', $statusPaid);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $purchaseProductSuppliers = $query->orderBy('order_date', 'desc')->get();

        // Calculate totals
        $totalAmount = $purchaseProductSuppliers->sum('total_amount');
        $totalOrders = $purchaseProductSuppliers->count();
        $totalQuantity = $purchaseProductSuppliers->sum('quantity');

        // Group by status for additional insights
        $statusSummary = $purchaseProductSuppliers->groupBy('status')->map(function ($items) {
            return [
                'count' => $items->count(),
                'amount' => $items->sum('total_amount')
            ];
        });

        // Group by supplier
        $supplierSummary = $purchaseProductSuppliers->groupBy('supplier.name')->map(function ($items) {
            return [
                'count' => $items->count(),
                'amount' => $items->sum('total_amount'),
                'quantity' => $items->sum('quantity')
            ];
        });

        // Get filter labels for display
        $filterLabels = $this->getFilterLabels($request);
        $companyInfo = $this->getCompanyInfo();

        return view('purchase-product-supplier.report', compact(
            'purchaseProductSuppliers',
            'fromDate',
            'untilDate',
            'totalAmount',
            'totalOrders',
            'totalQuantity',
            'statusSummary',
            'supplierSummary',
            'filterLabels',
            'companyInfo'
        ));
    }

    private function getFilterLabels(Request $request)
    {
        $labels = [];

        if ($request->supplier_id) {
            $supplier = Supplier::find($request->supplier_id);
            $labels['supplier'] = $supplier ? $supplier->name . ' (' . $supplier->code . ')' : 'Unknown';
        }

        if ($request->product_id) {
            $product = Product::find($request->product_id);
            $labels['product'] = $product ? $product->name . ' (' . $product->code . ')' : 'Unknown';
        }

        if ($request->type_po) {
            $labels['type_po'] = ucfirst($request->type_po) . ' Purchase';
        }

        if ($request->status_paid) {
            $labels['status_paid'] = ucfirst($request->status_paid);
        }

        if ($request->status) {
            $labels['status'] = $request->status;
        }

        return $labels;
    }
}
