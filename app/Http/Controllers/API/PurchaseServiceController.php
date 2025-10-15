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
     * Create new purchase service
     */
    public function purchaseService(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'order_date' => 'required|date',
            'type_po' => 'required|in:cash,credit',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.service_id' => 'required|integer|exists:services,id',
        ], [
            'name.required' => 'PO Name Is Required.',
            'order_date.required' => 'Order Date Is Required.',
            'order_date.date' => 'Order Date must be a valid date.',
            'type_po.required' => 'PO Type Is Required.',
            'type_po.in' => 'PO Type must be either cash or credit.',
            'items.required' => 'At least one item is required.',
            'items.min' => 'At least one item is required.',
            'items.*.service_id.required' => 'Product ID Is Required.',
            'items.*.service_id.exists' => 'Selected Product Not Found.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = auth()->user();

        // Generate PO Number
        $poNumber = ServicePurchase::generatePoNumber($user->id, $request->order_date);

        // Determine initial status based on type_po
        $initialStatus = $request->type_po === 'credit' ? 'Requested' : 'Draft';

        // Create Purchase Product
        $purchaseService = ServicePurchase::create([
            'po_number' => $poNumber,
            'name' => $request->name,
            'user_id' => $user->id,
            'order_date' => $request->order_date,
            'status' => $initialStatus, // Status berdasarkan type_po
            'type_po' => $request->type_po,
            'status_paid' => 'unpaid',
            'notes' => $request->notes,
            'total_amount' => 0, // Will be calculated by model
        ]);

        // Add items dengan unit_price dari Product
        foreach ($request->items as $item) {
            // Get service untuk ambil price
            $service = Service::find($item['service_id']);

            if (!$service) {
                // Hapus PO yang sudah terlanjur dibuat jika service tidak ditemukan
                $purchaseService->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Service with ID ' . $item['service_id'] . ' not found'
                ], 422);
            }

            $purchaseService->items()->create([
                'service_id' => $item['service_id'],
                'selling_price' => $service->price, // Ambil dari service
            ]);
        }

        // Hitung total amount setelah semua items ditambahkan
        $purchaseService->calculateTotal();

        // Load relationships for response
        $purchaseService->load(['user', 'items.product']);

        return response()->json([
            'success' => true,
            'data' => new PurchaseServiceResource($purchaseService),
            'message' => 'Purchase Order Created Successfully'
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
            $fileName = $purchaseService->po_number . '_' . time() . '.' . $type;
            $path = 'po_service/' . $fileName;

            // Store the image
            $stored = Storage::disk('public')->put($path, $decodedData);

            if (!$stored) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed To Store Payment Receipt.',
                ], 422);
            }

            // Update purchase product
            $purchaseService->status_paid = 'paid';

            if ($purchaseService->type_po === 'cash') {
                $purchaseService->status = 'Requested';
            }

            $purchaseService->bukti_tf = $path;

            if ($purchaseService->save()) {
                return response()->json([
                    'success' => true,
                    'data' => new PurchaseServiceResource($purchaseService),
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
