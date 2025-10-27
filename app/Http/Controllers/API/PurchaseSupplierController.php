<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PurchaseProductSupplier;
use App\Models\Product;
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
        $query = PurchaseProductSupplier::with(['user', 'supplier']);

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
        $limit = (int)($request->limit ?? 10);
        $page = (int)($request->page ?? 1);

        if ($request->has('start')) {
            $start = (int)($request->start ?? 0);
        } else {
            $start = ($page - 1) * $limit;
        }

        // Get total count
        $total = $query->count();

        // Get data using offset and limit
        $purchaseProductSuppliers = $query->offset($start)->limit($limit)->get();

        // Calculate page info
        $currentPage = (int)(($start / $limit) + 1);
        $lastPage = (int)ceil($total / $limit);

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

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = auth()->user();
        $query = PurchaseProductSupplier::with(['user', 'supplier', 'items.product']);

        $purchaseSupplier = $query->find($request->id);

        if (!$purchaseSupplier) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Supplier Not Found Or Access Denied'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new PurchaseSupplierResource($purchaseSupplier),
            'message' => 'Purchase Supplier Detail Retrieved Successfully'
        ]);
    }

    /**
     * Create new purchase supplier
     */
    public function purchaseSupplier(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'order_date' => 'required|date',
            'type_po' => 'required|in:cash,credit',
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ], [
            'name.required' => 'PO Name Is Required.',
            'order_date.required' => 'Order Date Is Required.',
            'order_date.date' => 'Order Date must be a valid date.',
            'type_po.required' => 'PO Type Is Required.',
            'type_po.in' => 'PO Type must be either cash or credit.',
            'supplier_id.required' => 'Supplier Is Required.',
            'supplier_id.exists' => 'Selected Supplier Not Found.',
            'items.required' => 'At least one item is required.',
            'items.min' => 'At least one item is required.',
            'items.*.product_id.required' => 'Product ID Is Required.',
            'items.*.product_id.exists' => 'Selected Product Not Found.',
            'items.*.quantity.required' => 'Quantity Is Required.',
            'items.*.quantity.numeric' => 'Quantity must be a number.',
            'items.*.quantity.min' => 'Quantity must be at least 0.01.',
            'items.*.unit_price.required' => 'Unit Price Is Required.',
            'items.*.unit_price.numeric' => 'Unit Price must be a number.',
            'items.*.unit_price.min' => 'Unit Price must be at least 0.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = auth()->user();

        // Generate PO Number menggunakan supplier_id
        $poNumber = PurchaseProductSupplier::generatePoNumber($request->supplier_id, $request->order_date);

        // Create Purchase Supplier
        $purchaseSupplier = PurchaseProductSupplier::create([
            'po_number' => $poNumber,
            'name' => $request->name,
            'user_id' => $user->id,
            'supplier_id' => $request->supplier_id,
            'order_date' => $request->order_date,
            'type_po' => $request->type_po,
            'status_paid' => 'unpaid',
            'notes' => $request->notes,
            'total_amount' => 0, // Will be calculated
        ]);

        // Add items
        foreach ($request->items as $item) {
            // Verify product exists
            $product = Product::find($item['product_id']);

            if (!$product) {
                // Delete PO if product not found
                $purchaseSupplier->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Product with ID ' . $item['product_id'] . ' not found'
                ], 422);
            }

            $purchaseSupplier->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                // total_price akan dihitung otomatis di model
            ]);
        }

        // Hitung total amount setelah semua items ditambahkan
        $purchaseSupplier->calculateTotal();

        // Load relationships for response
        $purchaseSupplier->load(['user', 'supplier', 'items.product']);

        return response()->json([
            'success' => true,
            'data' => new PurchaseSupplierResource($purchaseSupplier),
            'message' => 'Purchase Supplier Order Created Successfully'
        ]);
    }

    /**
     * Cancel purchase supplier (only for Requested and Processing status)
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
        $query = PurchaseProductSupplier::with(['user', 'supplier', 'items.product']);

        $purchaseSupplier = $query->find($request->id);

        if (!$purchaseSupplier) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Supplier Not Found Or Access Denied'
            ], 404);
        }

        // Check if PO can be cancelled
        if (!in_array($purchaseSupplier->status, ['Requested', 'Processing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Requested and Processing Purchase Orders can be cancelled. Current status: ' . $purchaseSupplier->status
            ], 422);
        }

        // Cancel the purchase order
        $purchaseSupplier->cancel();

        return response()->json([
            'success' => true,
            'data' => new PurchaseSupplierResource($purchaseSupplier),
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

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = auth()->user();
        $query = PurchaseProductSupplier::with(['user', 'supplier', 'items.product']);

        $purchaseSupplier = $query->find($request->id);

        if (!$purchaseSupplier) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Supplier Not Found Or Access Denied'
            ], 404);
        }

        try {
            // Handle base64 image upload
            $imageData = $request->bukti_tf;

            // Check if it's a data URL
            if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
                $imageData = substr($imageData, strpos($imageData, ',') + 1);
                $type = strtolower($type[1]);
            } else {
                $type = 'jpg';
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
            $fileName = $purchaseSupplier->po_number . '_' . time() . '.' . $type;
            $path = 'po_supplier/' . $fileName;

            // Store the image
            $stored = Storage::disk('public')->put($path, $decodedData);

            if (!$stored) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed To Store Payment Receipt.',
                ], 422);
            }

            // Update purchase supplier
            $purchaseSupplier->status_paid = 'paid';
            $purchaseSupplier->bukti_tf = $path;

            if ($purchaseSupplier->save()) {
                return response()->json([
                    'success' => true,
                    'data' => new PurchaseSupplierResource($purchaseSupplier),
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
     * Process purchase supplier (change status to Processing)
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
        $query = PurchaseProductSupplier::with(['user', 'supplier', 'items.product']);

        $purchaseSupplier = $query->find($request->id);

        if (!$purchaseSupplier) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Supplier Not Found Or Access Denied'
            ], 404);
        }

        // Check if status is valid for processing
        if ($purchaseSupplier->status !== 'Requested') {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Order must be in "Requested" status to be processed'
            ], 422);
        }

        try {
            // Process the purchase order
            $purchaseSupplier->process();

            return response()->json([
                'success' => true,
                'data' => new PurchaseSupplierResource($purchaseSupplier),
                'message' => 'Purchase Order Processed Successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Mark purchase supplier as received (change status to Received)
     */
    public function receivePurchase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'received_date' => 'nullable|date',
        ], [
            'id.required' => 'ID Is Required.',
            'received_date.date' => 'Received Date must be a valid date.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = auth()->user();
        $query = PurchaseProductSupplier::with(['user', 'supplier', 'items.product']);

        $purchaseSupplier = $query->find($request->id);

        if (!$purchaseSupplier) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Supplier Not Found Or Access Denied'
            ], 404);
        }

        // Check if status is valid for receiving
        if ($purchaseSupplier->status !== 'Processing') {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Order must be in "Processing" status to be received'
            ], 422);
        }

        // Update received date if provided
        if ($request->has('received_date')) {
            $purchaseSupplier->received_date = $request->received_date;
            $purchaseSupplier->save();
        }

        // Mark as received
        $purchaseSupplier->receive();

        return response()->json([
            'success' => true,
            'data' => new PurchaseSupplierResource($purchaseSupplier),
            'message' => 'Purchase Order Received Successfully'
        ]);
    }

    /**
     * Complete purchase supplier (change status to Done)
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
        $query = PurchaseProductSupplier::with(['user', 'supplier', 'items.product']);

        $purchaseSupplier = $query->find($request->id);

        if (!$purchaseSupplier) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Supplier Not Found Or Access Denied'
            ], 404);
        }

        // Check if status is valid for completion
        if ($purchaseSupplier->status !== 'Received') {
            return response()->json([
                'success' => false,
                'message' => 'Purchase Order must be in "Received" status to be completed'
            ], 422);
        }

        try {
            // Complete the purchase order
            $purchaseSupplier->done();

            return response()->json([
                'success' => true,
                'data' => new PurchaseSupplierResource($purchaseSupplier),
                'message' => 'Purchase Order Completed Successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
