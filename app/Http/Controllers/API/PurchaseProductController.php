<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseProductResource;
use App\Models\PurchaseProduct;
use App\Models\PurchaseProductItem;
use App\Models\Product;
use App\Models\ProductBatch;
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
        if ($user->hasRole('User')) {
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
     * Create new purchase product
     */
    public function purchaseProduct(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'order_date' => 'required|date',
            'type_po' => 'required|in:cash,credit',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01', // Ubah dari integer ke numeric
        ], [
            'name.required' => 'PO Name Is Required.',
            'order_date.required' => 'Order Date Is Required.',
            'order_date.date' => 'Order Date must be a valid date.',
            'type_po.required' => 'PO Type Is Required.',
            'type_po.in' => 'PO Type must be either cash or credit.',
            'items.required' => 'At least one item is required.',
            'items.min' => 'At least one item is required.',
            'items.*.product_id.required' => 'Product ID Is Required.',
            'items.*.product_id.exists' => 'Selected Product Not Found.',
            'items.*.quantity.required' => 'Quantity Is Required.',
            'items.*.quantity.numeric' => 'Quantity must be a number.',
            'items.*.quantity.min' => 'Quantity must be at least 0.01.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = auth()->user();

        // Generate PO Number
        $poNumber = PurchaseProduct::generatePoNumber($user->id, $request->order_date);

        // Determine initial status based on type_po
        $initialStatus = $request->type_po === 'credit' ? 'Requested' : 'Draft';

        // Create Purchase Product
        $purchaseProduct = PurchaseProduct::create([
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
            // Get product untuk ambil price
            $product = Product::find($item['product_id']);

            if (!$product) {
                // Hapus PO yang sudah terlanjur dibuat jika product tidak ditemukan
                $purchaseProduct->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Product with ID ' . $item['product_id'] . ' not found'
                ], 422);
            }

            $purchaseProduct->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $product->price, // Ambil dari product
                // total_price akan dihitung otomatis di model
                // cost_price, profit_amount, profit_margin akan diset saat process
            ]);
        }

        // Hitung total amount setelah semua items ditambahkan
        $purchaseProduct->calculateTotal();

        // Load relationships for response
        $purchaseProduct->load(['user', 'items.product']);

        return response()->json([
            'success' => true,
            'data' => new PurchaseProductResource($purchaseProduct),
            'message' => 'Purchase Order Created Successfully'
        ]);
    }

    /**
     * Cancel purchase product (only for Draft and Requested status)
     */
    public function cancelPurchase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ], [
            'id.required' => 'ID Is Required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = auth()->user();
        $query = PurchaseProduct::with(['user', 'items.product']);

        // Filter berdasarkan role
        if ($user->hasRole('User')) {
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

        // Check if PO can be cancelled (only Draft and Requested status can be cancelled)
        if (!in_array($purchaseProduct->status, ['Draft', 'Requested'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Draft or Requested Purchase Orders can be cancelled. Current status: ' . $purchaseProduct->status
            ], 422);
        }

        // Cancel the purchase order
        $purchaseProduct->cancel();

        return response()->json([
            'success' => true,
            'data' => new PurchaseProductResource($purchaseProduct),
            'message' => 'Purchase Order Cancelled Successfully'
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
        if ($user->hasRole('User')) {
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

            if ($purchaseProduct->type_po === 'cash') {
                $purchaseProduct->status = 'Requested';
            }

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

    /**
     * Process purchase order (change status to Processing)
     */
    public function processPurchase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ], [
            'id.required' => 'ID Is Required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = auth()->user();
        $query = PurchaseProduct::with(['user', 'items.product']);

        // Filter berdasarkan role
        if ($user->hasRole('User')) {
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

        // Check if status is valid for processing
        if ($purchaseProduct->status !== 'Requested') {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Order must be in "Requested" status to be processed'
            ], 422);
        }

        // Check if can be processed
        $processCheck = $purchaseProduct->canBeProcessed();

        if (!$processCheck['can_process']) {
            $message = 'Cannot process this Purchase Order. ';

            // Add insufficient stock details
            if (!empty($processCheck['insufficient_items'])) {
                $message .= 'Insufficient stock for: ';
                $stockDetails = [];
                foreach ($processCheck['insufficient_items'] as $item) {
                    $stockDetails[] = sprintf(
                        '%s (Required: %d, Available: %d)',
                        $item['product_name'],
                        $item['required'],
                        $item['available']
                    );
                }
                $message .= implode(', ', $stockDetails);
            }

            // Add validation errors
            if (!empty($processCheck['validation_errors'])) {
                $message .= ' ' . implode('. ', $processCheck['validation_errors']);
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'details' => $processCheck
            ], 422);
        }

        // Process the purchase order
        if ($purchaseProduct->process()) {
            // Reload untuk mendapatkan data cost analysis yang baru di-set
            $purchaseProduct->load(['user', 'items.product']);

            return response()->json([
                'success' => true,
                'data' => new PurchaseProductResource($purchaseProduct),
                'message' => 'Purchase Order Processed Successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed To Process Purchase Order'
        ], 422);
    }

    /**
     * Ship purchase order (change status to Shipped)
     */
    public function shipPurchase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'expected_delivery_date' => 'nullable|date',
        ], [
            'id.required' => 'ID Is Required.',
            'expected_delivery_date.date' => 'Expected Delivery Date must be a valid date.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = auth()->user();
        $query = PurchaseProduct::with(['user', 'items.product']);

        // Filter berdasarkan role
        if ($user->hasRole('User')) {
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

        // Check if status is valid for shipping
        if ($purchaseProduct->status !== 'Processing') {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Order must be in "Processing" status to be shipped'
            ], 422);
        }

        // Update expected delivery date if provided
        if ($request->has('expected_delivery_date')) {
            $purchaseProduct->expected_delivery_date = $request->expected_delivery_date;
            $purchaseProduct->save();
        }

        // Check if can be shipped
        $shipCheck = $purchaseProduct->canBeShipped();

        if (!$shipCheck['can_ship']) {
            $message = 'Cannot ship this Purchase Order. ';

            if (!empty($shipCheck['validation_errors'])) {
                $message .= implode('. ', $shipCheck['validation_errors']);
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'details' => $shipCheck
            ], 422);
        }

        // Ship the purchase order
        if ($purchaseProduct->ship()) {
            return response()->json([
                'success' => true,
                'data' => new PurchaseProductResource($purchaseProduct),
                'message' => 'Purchase Order Shipped Successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed To Ship Purchase Order'
        ], 422);
    }

    /**
     * Mark purchase order as received (change status to Received)
     */
    public function receivePurchase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ], [
            'id.required' => 'ID Is Required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = auth()->user();
        $query = PurchaseProduct::with(['user', 'items.product']);

        // Filter berdasarkan role
        if ($user->hasRole('User')) {
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

        // Check if status is valid for receiving
        if ($purchaseProduct->status !== 'Shipped') {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Order must be in "Shipped" status to be received'
            ], 422);
        }

        // Mark as received
        $purchaseProduct->received();

        return response()->json([
            'success' => true,
            'data' => new PurchaseProductResource($purchaseProduct),
            'message' => 'Purchase Order Received Successfully'
        ]);
    }

    /**
     * Complete purchase order (change status to Done)
     */
    public function completePurchase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ], [
            'id.required' => 'ID Is Required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = auth()->user();
        $query = PurchaseProduct::with(['user', 'items.product']);

        // Filter berdasarkan role
        if ($user->hasRole('User')) {
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

        // Check if status is valid for completion
        if ($purchaseProduct->status !== 'Received') {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Order must be in "Received" status to be completed'
            ], 422);
        }

        // Check if can be completed
        $completeCheck = $purchaseProduct->canBeCompleted();

        if (!$completeCheck['can_complete']) {
            $message = 'Cannot complete this Purchase Order. ';

            if (!empty($completeCheck['validation_errors'])) {
                $message .= implode('. ', $completeCheck['validation_errors']);
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'details' => $completeCheck
            ], 422);
        }

        // Complete the purchase order
        if ($purchaseProduct->done()) {
            return response()->json([
                'success' => true,
                'data' => new PurchaseProductResource($purchaseProduct),
                'message' => 'Purchase Order Completed Successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed To Complete Purchase Order'
        ], 422);
    }
}
