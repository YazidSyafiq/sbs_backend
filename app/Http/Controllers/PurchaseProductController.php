<?php

namespace App\Http\Controllers;

use App\Models\PurchaseProduct;
use App\Models\Product;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PurchaseProductController extends Controller
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

    public function invoice(Request $request, PurchaseProduct $purchaseProduct)
    {
        $purchaseProduct->load(['user.branch', 'items.product']);
        $companyInfo = $this->getCompanyInfo();

        // Detect if accessed from Flutter/Mobile
        $isFromMobile = $this->isMobileAccess($request);

        return view('purchase-product.invoice', compact(
            'purchaseProduct',
            'companyInfo',
            'isFromMobile'
        ));
    }

    public function faktur(Request $request, PurchaseProduct $purchaseProduct)
    {
        $purchaseProduct->load(['user.branch', 'items.product']);
        $companyInfo = $this->getCompanyInfo();

        // Detect if accessed from Flutter/Mobile
        $isFromMobile = $this->isMobileAccess($request);

        return view('purchase-product.faktur', compact(
            'purchaseProduct',
            'companyInfo',
            'isFromMobile'
        ));
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

    public function report(Request $request)
    {
        // Get filter parameters
        $fromDate = $request->from_date ? Carbon::parse($request->from_date) : Carbon::now()->startOfMonth();
        $untilDate = $request->until_date ? Carbon::parse($request->until_date) : Carbon::now();
        $piutangStatus = $request->piutang_status ?? 'all';
        $productId = $request->product_id;
        $typePo = $request->type_po;
        $status = $request->status;
        $minTotalAmount = $request->min_total_amount;
        $maxTotalAmount = $request->max_total_amount;

        // Query untuk mendapatkan Purchase Product dalam periode tertentu
        $purchaseProductQuery = PurchaseProduct::with(['items.product', 'user.branch'])
            ->whereBetween('order_date', [$fromDate, $untilDate]);

        // Apply filters pada Purchase Product
        if ($piutangStatus === 'has_piutang') {
            $purchaseProductQuery->where('status_paid', 'unpaid');
        } elseif ($piutangStatus === 'no_piutang') {
            $purchaseProductQuery->where('status_paid', 'paid');
        }

        if ($typePo) {
            $purchaseProductQuery->where('type_po', $typePo);
        }

        if ($status) {
            $purchaseProductQuery->where('status', $status);
        }

        if ($minTotalAmount) {
            $purchaseProductQuery->where('total_amount', '>=', $minTotalAmount);
        }

        if ($maxTotalAmount) {
            $purchaseProductQuery->where('total_amount', '<=', $maxTotalAmount);
        }

        if ($productId) {
            $purchaseProductQuery->whereHas('items.product', function($q) use ($productId) {
                $q->where('id', $productId);
            });
        }

        $purchaseProducts = $purchaseProductQuery->orderBy('order_date', 'desc')->get();

        // Filter items berdasarkan product criteria jika ada
        $filteredData = collect();
        foreach ($purchaseProducts as $purchase) {
            $filteredItems = $purchase->items;

            // Filter items jika product_id dipilih
            if ($productId) {
                $filteredItems = $filteredItems->filter(function($item) use ($productId) {
                    return $item->product_id == $productId;
                });
            }

            if ($filteredItems->count() > 0) {
                $filteredData->push((object) [
                    'purchase' => $purchase,
                    'filtered_items' => $filteredItems->values(),
                    'items_count' => $filteredItems->count(),
                    'items_total' => $filteredItems->sum('total_price'),
                    'items_cost' => $filteredItems->sum(function($item) {
                        return $item->cost_price * $item->quantity;
                    }),
                    'items_profit' => $filteredItems->sum('profit_amount'),
                ]);
            }
        }

        // Calculate totals
        $totalOrders = $filteredData->count();
        $totalItems = $filteredData->sum('items_count');
        $totalAmount = $filteredData->sum('items_total');
        $totalCost = $filteredData->sum('items_cost');
        $totalProfit = $filteredData->sum('items_profit');
        $averageOrderValue = $totalOrders > 0 ? $totalAmount / $totalOrders : 0;

        // Calculate unpaid/paid totals
        $totalUnpaidAmount = $filteredData->filter(function($data) {
            return $data->purchase->status_paid === 'unpaid';
        })->sum('items_total');

        $totalPaidAmount = $filteredData->filter(function($data) {
            return $data->purchase->status_paid === 'paid';
        })->sum('items_total');

        // Product summary jika tidak filter by product
        $productSummary = collect();
        if (!$productId && $filteredData->count() > 0) {
            $allItems = $filteredData->flatMap(function($data) {
                return $data->filtered_items;
            });

            $productSummary = $allItems->groupBy('product_id')->map(function($items, $productId) {
                $product = $items->first()->product;
                return (object) [
                    'product' => $product,
                    'total_quantity' => $items->sum('quantity'),
                    'total_amount' => $items->sum('total_price'),
                    'total_cost' => $items->sum(function($item) {
                        return $item->cost_price * $item->quantity;
                    }),
                    'total_profit' => $items->sum('profit_amount'),
                    'order_count' => $items->count(),
                ];
            });
        }

        // Status summary
        $statusSummary = $filteredData->groupBy('purchase.status')->map(function($items) {
            return [
                'count' => $items->count(),
                'amount' => $items->sum('items_total'),
            ];
        });

        // Variables untuk compatibility
        $totalPiutang = $totalUnpaidAmount;
        $totalPoAmount = $totalAmount;

        // Get filter labels for display
        $filterLabels = $this->getFilterLabels($request);
        $companyInfo = $this->getCompanyInfo();

        return view('purchase-product.report', compact(
            'filteredData',
            'fromDate',
            'untilDate',
            'totalOrders',
            'totalItems',
            'totalAmount',
            'totalCost',
            'totalProfit',
            'totalUnpaidAmount',
            'totalPaidAmount',
            'averageOrderValue',
            'productSummary',
            'statusSummary',
            'totalPiutang',
            'totalPoAmount',
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

        if ($request->product_id) {
            $product = Product::find($request->product_id);
            $labels['product'] = $product ? $product->name . ' (' . $product->code . ')' : 'Unknown Product';
        }

        if ($request->type_po) {
            $labels['type_po'] = ucfirst($request->type_po) . ' Purchase';
        }

        if ($request->status) {
            $labels['status'] = $request->status;
        }

        if ($request->min_total_amount) {
            $labels['min_total_amount'] = 'Min Amount: Rp ' . number_format($request->min_total_amount, 0, ',', '.');
        }

        if ($request->max_total_amount) {
            $labels['max_total_amount'] = 'Max Amount: Rp ' . number_format($request->max_total_amount, 0, ',', '.');
        }

        return $labels;
    }
}
