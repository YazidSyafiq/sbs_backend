<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PurchaseProductController;

Route::get('/', function () {
    return redirect('admin/login');
});

// routes/web.php
Route::get('/purchase-product/{purchaseProduct}/invoice', [PurchaseProductController::class, 'invoice'])->name('purchase-product.invoice');
Route::get('/purchase-product/{purchaseProduct}/faktur', [PurchaseProductController::class, 'faktur'])->name('purchase-product.faktur');
