<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PurchaseProductController;
use App\Http\Controllers\PurchaseServiceController;
use App\Http\Controllers\PurchaseProductSupplierController;

Route::get('/', function () {
    return redirect('admin/login');
});

// routes/web.php
Route::get('/purchase-product/{purchaseProduct}/invoice', [PurchaseProductController::class, 'invoice'])->name('purchase-product.invoice');
Route::get('/purchase-product/{purchaseProduct}/faktur', [PurchaseProductController::class, 'faktur'])->name('purchase-product.faktur');
Route::get('/purchase-service/{purchaseService}/invoice', [PurchaseServiceController::class, 'invoice'])->name('purchase-service.invoice');
Route::get('/purchase-service/{purchaseService}/faktur', [PurchaseServiceController::class, 'faktur'])->name('purchase-service.faktur');
Route::get('/purchase-supplier/{purchaseProduct}/faktur-supplier', [PurchaseProductSupplierController::class, 'faktur'])->name('purchase-product-supplier.faktur');

// Tambahkan route untuk report
Route::get('/purchase-service/report', [PurchaseServiceController::class, 'report'])
    ->name('purchase-service.report')
    ->middleware('auth');

// Route baru untuk Purchase Product Supplier Report
Route::get('/purchase-product-supplier/report', [PurchaseProductSupplierController::class, 'report'])
    ->name('purchase-product-supplier.report')
    ->middleware('auth');
