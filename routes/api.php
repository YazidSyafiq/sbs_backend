<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use  App\Http\Controllers\API\AuthController;

// Login
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // Change Password
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // FCM Token Register
    Route::post('/fcm-token', [AuthController::class, 'registerFCMToken']);

});
