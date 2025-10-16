<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use  App\Http\Controllers\API\AuthController;
use  App\Http\Controllers\API\ProfileController;
use  App\Http\Controllers\API\ProductController;
use  App\Http\Controllers\API\PurchaseProductController;
use  App\Http\Controllers\API\PurchaseServiceController;
use  App\Http\Controllers\API\ServiceController;
use  App\Http\Controllers\API\SupplierController;
use  App\Http\Controllers\API\TechnicianController;

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
});
