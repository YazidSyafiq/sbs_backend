<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseProductResource;
use App\Models\PurchaseProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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
            'message' => 'Purchase Products Retrieved Successfully'
        ]);
    }

    /**
     * Get single purchase product detail using POST
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
                'message' => 'Purchase Product Not Found Or Access Denied'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new PurchaseProductResource($purchaseProduct),
            'message' => 'Purchase Product Detail Retrieved Successfully'
        ]);
    }

    /**
     * Update payment status and upload payment receipt
     */
    public function updatePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'bukti_tf' => 'required|string',
        ], [
            'id.required' => 'ID Is Required.',
            'bukti_tf.required' => 'Payment Receipt Is Required.'
        ]);

        // Jika validasi gagal, kembalikan response dengan format yang diinginkan
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

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
                'message' => 'Purchase Product Not Found Or Access Denied'
            ], 404);
        }

        try {
            // Handle base64 image upload
            $imageData = $request->bukti_tf;

            // Check if it's a data URL (data:image/jpeg;base64,...)
            if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
                // Extract the base64 encoded binary data
                $imageData = substr($imageData, strpos($imageData, ',') + 1);
                $type = strtolower($type[1]); // jpg, png, gif
            } else {
                // If no data URL prefix, assume it's raw base64
                $type = 'jpg'; // default type
            }

            // Validate image type
            if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Image Type. Only JPG, JPEG, PNG And GIF Are Allowed.',
                ], 422);
            }

            // Decode base64
            $decodedData = base64_decode($imageData);

            if ($decodedData === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Base64 Image Data.',
                ], 422);
            }

            // Generate filename using PO number
            $fileName = $purchaseProduct->po_number . '_' . time() . '.' . $type;
            $path = 'po_product/' . $fileName;

            // Store the image
            $stored = Storage::disk('public')->put($path, $decodedData);

            if (!$stored) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed To Store Payment Receipt.',
                ], 422);
            }

            // Update purchase product
            $purchaseProduct->status_paid = 'paid';
            $purchaseProduct->bukti_tf = $path;

            if ($purchaseProduct->save()) {
                return response()->json([
                    'success' => true,
                    'data' => new PurchaseProductResource($purchaseProduct),
                    'message' => 'Payment Updated Successfully'
                ]);
            } else {
                // If save failed, delete the uploaded file
                Storage::disk('public')->delete($path);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed To Update Payment Status'
                ], 422);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error Processing Payment Receipt: ' . $e->getMessage(),
            ], 422);
        }
    }
}
