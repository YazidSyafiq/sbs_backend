<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use  App\Http\Controllers\API\AuthController;
use  App\Http\Controllers\API\ProfileController;

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

});
