<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use  App\Http\Controllers\API\AuthController;
use  App\Http\Controllers\API\ProfileController;
use  App\Http\Controllers\API\ProductController;
use  App\Http\Controllers\API\ServiceController;

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

    // Get Service
    Route::get('/service', [ServiceController::class, 'getService']);
});
