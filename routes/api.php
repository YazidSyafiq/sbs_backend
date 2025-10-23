<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use  App\Http\Controllers\API\AuthController;
use  App\Http\Controllers\API\ProfileController;
use  App\Http\Controllers\API\ProductController;
use  App\Http\Controllers\API\BranchController;
use  App\Http\Controllers\API\PurchaseProductController;
use  App\Http\Controllers\API\PurchaseServiceController;
use  App\Http\Controllers\API\PurchaseSupplierController;
use  App\Http\Controllers\API\ServiceController;
use  App\Http\Controllers\API\SupplierController;
use  App\Http\Controllers\API\TechnicianController;
use  App\Http\Controllers\API\POReportProductController;
use  App\Http\Controllers\API\POReportServiceController;

// Login
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // Change Password
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // FCM Token Register
    Route::post('/fcm-token', [AuthController::class, 'registerFCMToken']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'getProfile']);

    // Update Profile
    Route::post('/profile-update', [ProfileController::class, 'updateProfile']);

    // Get Branch
    Route::get('/branch', [BranchController::class, 'getBranch']);

    // Get Product
    Route::get('/product', [ProductController::class, 'getProduct']);

    // Get Purchase Product List
    Route::post('/purchase-products/list', [PurchaseProductController::class, 'getList']);

    // Get Purchase Product Detail
    Route::post('/purchase-products/detail', [PurchaseProductController::class, 'getDetail']);

    // Purchase Product
    Route::post('/purchase-products/purchase', [PurchaseProductController::class, 'purchaseProduct']);

    // Purchase Product Cancel
    Route::post('/purchase-products/cancel', [PurchaseProductController::class, 'cancelPurchase']);

    // Purchase Product Payment
    Route::post('/purchase-products/payment', [PurchaseProductController::class, 'updatePayment']);

    // Purchase Product Process
    Route::post('/purchase-products/process', [PurchaseProductController::class, 'processPurchase']);

    // Purchase Product Ship
    Route::post('/purchase-products/ship', [PurchaseProductController::class, 'shipPurchase']);

    // Purchase Product Receive
    Route::post('/purchase-products/receive', [PurchaseProductController::class, 'receivePurchase']);

    // Purchase Product Complete
    Route::post('/purchase-products/complete', [PurchaseProductController::class, 'completePurchase']);

    // Get Service
    Route::get('/service', [ServiceController::class, 'getService']);

    // Get Technician
    Route::get('/technician', [TechnicianController::class, 'getTechnician']);

    // Get Purchase Service List
    Route::post('/purchase-services/list', [PurchaseServiceController::class, 'getList']);

    // Get Purchase Service Detail
    Route::post('/purchase-services/detail', [PurchaseServiceController::class, 'getDetail']);

    // Purchase Service
    Route::post('/purchase-services/purchase', [PurchaseServiceController::class, 'purchaseService']);

    // Update Technician ID Purchase Service Item
    Route::post('/purchase-services/technician', [PurchaseServiceController::class, 'selectTechnician']);

    // Purchase Service Payment
    Route::post('/purchase-services/payment', [PurchaseServiceController::class, 'updatePayment']);

    // Purchase Service Cancel
    Route::post('/purchase-services/cancel', [PurchaseServiceController::class, 'cancelPurchase']);

    // Purchase Service Approve
    Route::post('/purchase-services/approve', [PurchaseServiceController::class, 'approvePurchase']);

    // Purchase Service Progress
    Route::post('/purchase-services/progress', [PurchaseServiceController::class, 'progressPurchase']);

    // Purchase Service Complete
    Route::post('/purchase-services/complete', [PurchaseServiceController::class, 'completePurchase']);

    // Supplier
    Route::get('/supplier', [SupplierController::class, 'getSupplier']);

    // Get Purchase Supplier List
    Route::post('/purchase-suppliers/list', [PurchaseSupplierController::class, 'getList'])
        ->name('purchase-suppliers.getList');;

    // Get Purchase Supplier Detail
    Route::post('/purchase-suppliers/detail', [PurchaseSupplierController::class, 'getDetail'])
        ->name('purchase-suppliers.getDetail');

    // Purchase Supplier
    Route::post('/purchase-suppliers/purchase', [PurchaseSupplierController::class, 'purchaseSupplier']);

    // Purchase Supplier Payment
    Route::post('/purchase-suppliers/payment', [PurchaseSupplierController::class, 'updatePayment']);

    // Purchase Supplier Cancel
    Route::post('/purchase-suppliers/cancel', [PurchaseSupplierController::class, 'cancelPurchase']);

    // Purchase Supplier Process
    Route::post('/purchase-suppliers/process', [PurchaseSupplierController::class, 'processPurchase']);

    // Purchase Supplier Receive
    Route::post('/purchase-suppliers/receive', [PurchaseSupplierController::class, 'receivePurchase']);

    // Purchase Supplier Complete
    Route::post('/purchase-suppliers/complete', [PurchaseSupplierController::class, 'completePurchase']);

    // PO Product Reports
    Route::post('/reports/po-product/overview', [POReportProductController::class, 'getOverview']);
    Route::post('/reports/po-product/trends', [POReportProductController::class, 'getTrends']);

    // PO Service Reports
    Route::post('/reports/po-service/overview', [POReportServiceController::class, 'getOverview']);
    Route::post('/reports/po-service/trends', [POReportServiceController::class, 'getTrends']);
});
