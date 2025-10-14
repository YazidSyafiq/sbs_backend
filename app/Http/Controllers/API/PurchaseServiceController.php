<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ServicePurchase;
use App\Models\ServicePurchaseItem;
use App\Models\Technician;
use App\Http\Resources\PurchaseServiceResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PurchaseServiceController extends Controller
{
    /**
     * Get list of purchase services with pagination using POST
     */
    public function getList(Request $request)
    {
        $user = auth()->user();
        $query = ServicePurchase::with('user');

        // Filter berdasarkan role
        if ($user->hasRole('User')) {
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
        $purchaseServices = $query->offset($start)->limit($limit)->get();

        // Calculate page info
        $currentPage = (int)(($start / $limit) + 1);
        $lastPage = (int)ceil($total / $limit);

        // Build response dengan info pagination
        return response()->json([
            'success' => true,
            'data' => PurchaseServiceResource::collection($purchaseServices),
            'meta' => [
                'total' => (int)$total,
                'start' => (int)$start,
                'limit' => (int)$limit,
                'page' => (int)$currentPage,
                'last_page' => (int)$lastPage,
                'has_more' => ($start + $limit) < $total
            ],
            'message' => 'Purchase Services Retrieved Successfully'
        ]);
    }

    /**
     * Get single purchase Service detail using POST
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
        $query = ServicePurchase::with(['user', 'items.service']);

        // Filter berdasarkan role
        if ($user->hasRole('User')) {
            $query->whereHas('user', function($q) use ($user) {
                $q->where('branch_id', $user->branch_id);
            });
        }

        $purchaseService = $query->find($request->id);

        if (!$purchaseService) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Service Not Found Or Access Denied'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new PurchaseServiceResource($purchaseService),
            'message' => 'Purchase Service Detail Retrieved Successfully'
        ]);
    }

    /**
     * Select Technician for Service Purchase Item
     */
    public function selectTechnician(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|integer',
            'technician_id' => 'required|integer',
        ], [
            'item_id.required' => 'Item ID Is Required.',
            'technician_id.required' => 'Technician ID Is Required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Get service purchase item
        $purchaseItem = ServicePurchaseItem::find($request->item_id);

        // Get Technician
        $technician = Technician::find($request->technician_id);

        // Update technician_id
        $purchaseItem->technician_id = $request->technician_id;
        $purchaseItem->cost_price = $technician->price;

        $purchaseItem->save();

        return response()->json([
            'success' => true,
            'message' => 'Technician Selected Successfully'
        ]);
    }
}
