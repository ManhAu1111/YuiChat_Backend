<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Controllers\GoogleAuthController;

Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/google', [GoogleAuthController::class, 'verifyGoogleToken']);

Route::post('/forgot-password/send-otp', [AuthController::class, 'sendForgotPasswordOtp']);
Route::post('/forgot-password/verify-otp', [AuthController::class, 'verifyForgotPasswordOtp']);
Route::post('/forgot-password/reset', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});
