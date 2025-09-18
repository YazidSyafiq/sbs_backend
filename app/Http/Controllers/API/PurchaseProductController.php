<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseProductResource;
use App\Models\PurchaseProduct;
use Illuminate\Http\Request;

class PurchaseProductController extends Controller
{
    /**
     * Get list of purchase products with pagination using POST
     */
    public function getList(Request $request)
    {
        $user = auth()->user();
        $query = PurchaseProduct::with('user');

        // Filter berdasarkan role
        if ($user->hasRole('user')) {
            // User dengan role 'user' hanya bisa lihat data sesuai branch nya
            $query->whereHas('user', function($q) use ($user) {
                $q->where('branch_id', $user->branch_id);
            });
        }
        // Selain role 'user', bisa lihat semua data

        // Optional filters dari request body
        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->type_po) {
            $query->where('type_po', $request->type_po);
        }

        if ($request->status_paid) {
            $query->where('status_paid', $request->status_paid);
        }

        // Search by PO number only
        if ($request->search) {
            $query->where('po_number', 'like', "%{$request->search}%");
        }

        // Date range filter
        if ($request->date_from) {
            $query->whereDate('order_date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('order_date', '<=', $request->date_to);
        }

        // Order by latest
        $query->orderBy('created_at', 'desc');

        // Pagination handling dengan limit, page, dan start
        $limit = (int)($request->limit ?? 10); // Default 10 data per request
        $page = (int)($request->page ?? 1); // Default page 1

        // Calculate start from page if not provided
        if ($request->has('start')) {
            $start = (int)($request->start ?? 0);
        } else {
            // Calculate start from page number
            $start = ($page - 1) * $limit;
        }

        // Get total count
        $total = $query->count();

        // Get data using offset and limit
        $purchaseProducts = $query->offset($start)->limit($limit)->get();

        // Calculate page info
        $currentPage = (int)(($start / $limit) + 1);
        $lastPage = (int)ceil($total / $limit);

        // Build response dengan info pagination
        return response()->json([
            'success' => true,
            'data' => PurchaseProductResource::collection($purchaseProducts),
            'meta' => [
                'total' => (int)$total,
                'start' => (int)$start,
                'limit' => (int)$limit,
                'page' => (int)$currentPage,
                'last_page' => (int)$lastPage,
                'has_more' => ($start + $limit) < $total
            ],
            'message' => 'Purchase products retrieved successfully'
        ]);
    }

    /**
     * Get single purchase product detail using POST
     */
    public function getDetail(Request $request)
    {
        $request->validate([
            'id' => 'required|integer'
        ]);

        $user = auth()->user();
        $query = PurchaseProduct::with(['user', 'items.product']);

        // Filter berdasarkan role
        if ($user->hasRole('user')) {
            $query->whereHas('user', function($q) use ($user) {
                $q->where('branch_id', $user->branch_id);
            });
        }

        $purchaseProduct = $query->find($request->id);

        if (!$purchaseProduct) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase product not found or access denied'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new PurchaseProductResource($purchaseProduct),
            'message' => 'Purchase product detail retrieved successfully'
        ]);
    }
}
