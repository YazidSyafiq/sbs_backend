<?php
// app/Http/Controllers/PurchaseProductSupplierController.php

namespace App\Http\Controllers;

use App\Models\PurchaseProductSupplier;
use App\Models\Supplier;
use App\Models\Product;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PurchaseProductSupplierController extends Controller
{
    /**
     * Get company info from environment variables
     */
    private function getCompanyInfo(): array
    {
        return [
            'name' => env('COMPANY_NAME', 'PT. Example Company'),
            'address' => env('COMPANY_ADDRESS', 'Jl. Contoh No. 123'),
            'city' => env('COMPANY_CITY', 'Jakarta'),
            'phone' => env('COMPANY_PHONE', '021-12345678'),
        ];
    }

    /**
     * Check if request is from mobile
     */
    private function isMobileAccess(): bool
    {
        return request()->has('mobile') && request()->mobile == 'true';
    }

    /**
     * Generate report view
     */
    public function report(Request $request)
    {
        $fromDate = $request->from_date ? Carbon::parse($request->from_date) : Carbon::now()->startOfMonth();
        $untilDate = $request->until_date ? Carbon::parse($request->until_date) : Carbon::now();

        $query = PurchaseProductSupplier::with(['supplier', 'items.product', 'user'])
            ->whereDate('order_date', '>=', $fromDate)
            ->whereDate('order_date', '<=', $untilDate)
            ->whereNotIn('status', ['Cancelled']);

        // Apply filters
        if ($request->supplier_id) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->product_id) {
            $query->whereHas('items', function($q) use ($request) {
                $q->where('product_id', $request->product_id);
            });
        }

        if ($request->type_po) {
            $query->where('type_po', $request->type_po);
        }

        if ($request->status_paid) {
            $query->where('status_paid', $request->status_paid);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $purchaseProductSuppliers = $query->orderBy('order_date', 'desc')->get();

        // Calculate totals
        $totalOrders = $purchaseProductSuppliers->count();
        $totalAmount = $purchaseProductSuppliers->sum('total_amount');
        $totalQuantity = $purchaseProductSuppliers->sum(function($po) {
            return $po->items->sum('quantity');
        });

        // Filter labels for display
        $filterLabels = [];
        if ($request->supplier_id) {
            $supplier = Supplier::find($request->supplier_id);
            $filterLabels['supplier'] = $supplier ? $supplier->name : 'Unknown';
        }
        if ($request->product_id) {
            $product = Product::find($request->product_id);
            $filterLabels['product'] = $product ? $product->name : 'Unknown';
        }
        if ($request->type_po) {
            $filterLabels['type_po'] = ucfirst($request->type_po);
        }
        if ($request->status_paid) {
            $filterLabels['payment_status'] = ucfirst($request->status_paid);
        }
        if ($request->status) {
            $filterLabels['status'] = $request->status;
        }

        $companyInfo = $this->getCompanyInfo();

        return view('purchase-product-supplier.report', compact(
            'purchaseProductSuppliers',
            'fromDate',
            'untilDate',
            'totalOrders',
            'totalAmount',
            'totalQuantity',
            'filterLabels',
            'companyInfo'
        ));
    }

    /**
     * Generate faktur view
     */
    public function faktur(PurchaseProductSupplier $purchaseProduct)
    {
        $purchaseProduct->load(['supplier', 'items.product', 'user']);

        $companyInfo = $this->getCompanyInfo();
        $isFromMobile = $this->isMobileAccess();

        return view('purchase-product-supplier.faktur', compact(
            'purchaseProduct',
            'companyInfo',
            'isFromMobile'
        ));
    }
}
