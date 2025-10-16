<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PurchaseProductSupplier;
use App\Http\Resources\PurchaseSupplierResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PurchaseSupplierController extends Controller
{
    /**
     * Get list of purchase product suppliers with pagination using POST
     */
    public function getList(Request $request)
    {
        $user = auth()->user();
        $query = PurchaseProductSupplier::with('user');

        // Optional filters dari request body
        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->supplier_id) {
            $query->where('supplier_id', $request->supplier_id);
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
        $purchaseProductSuppliers = $query->offset($start)->limit($limit)->get();

        // Calculate page info
        $currentPage = (int)(($start / $limit) + 1);
        $lastPage = (int)ceil($total / $limit);

        // Build response dengan info pagination
        return response()->json([
            'success' => true,
            'data' => PurchaseSupplierResource::collection($purchaseProductSuppliers),
            'meta' => [
                'total' => (int)$total,
                'start' => (int)$start,
                'limit' => (int)$limit,
                'page' => (int)$currentPage,
                'last_page' => (int)$lastPage,
                'has_more' => ($start + $limit) < $total
            ],
            'message' => 'Purchase Product Suppliers Retrieved Successfully'
        ]);
    }

    /**
     * Get single purchase supplier detail using POST
     */
    public function getDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ], [
            'id.required' => 'ID Is Required.',
        ]);

        // Jika validasi gagal, kembalikan response dengan format yang diinginkan
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = auth()->user();
        $query = PurchaseProductSupplier::with('user');

        $purchaseProduct = $query->find($request->id);

        if (!$purchaseProduct) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Supplier Not Found Or Access Denied'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new PurchaseSupplierResource($purchaseProduct),
            'message' => 'Purchase Supplier Detail Retrieved Successfully'
        ]);
    }
}
