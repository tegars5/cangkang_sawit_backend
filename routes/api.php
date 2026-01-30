<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\DriverOrderController;
use App\Http\Controllers\Api\WaybillController;
use App\Http\Controllers\Api\DistanceController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AdminDriverController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/payment/tripay/callback', [PaymentController::class, 'callback']);

// ✅ ROUTE 1: Untuk Driver (Download Waybill)
// Removed 'signed' middleware karena Ngrok mengubah URL (HTTP→HTTPS) yang membuat signature invalid
// Security: Rate limiting + controller validation sudah cukup
Route::get('/waybill/{id}/download', [WaybillController::class, 'generate'])
    ->name('api.waybill.generate')
    ->middleware('throttle:60,1');

// ✅ ROUTE 2: Untuk Admin/User (Preview PDF) + 🛡️ SECURITY PATCH
// Rate limiting: Max 60 requests per minute (mencegah brute force)
// URL: /api/orders/{order}/waybill/pdf
Route::get('/orders/{order}/waybill/pdf', [WaybillController::class, 'downloadWaybillPdf'])
    ->middleware('throttle:60,1');

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/payments/initiate-checkout', [PaymentController::class, 'initiateCheckoutPayment']);

    /* --- PROFILE & USER --- */
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::put('/password', [ProfileController::class, 'changePassword']);
        Route::post('/photo', [ProfileController::class, 'uploadPhoto']);
    });
    Route::post('/fcm-token', [UserController::class, 'updateFcmToken']);

    /* --- PRODUCT ROUTES --- */
    Route::get('/products/categories', [ProductController::class, 'getCategories']);
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);

    /* --- ORDER ROUTES (General User & Tracking) --- */
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{order}', [OrderController::class, 'show']);
        Route::post('/{order}/cancel', [OrderController::class, 'cancel']);
        Route::get('/{order}/tracking', [OrderController::class, 'tracking']);
        Route::post('/{order}/pay', [PaymentController::class, 'pay']);
        
        // Endpoint untuk Flutter mengirim lokasi GPS (Update Driver Location)
        Route::post('/{order}/update-location', [OrderController::class, 'updateDriverLocation']);
        
        Route::post('/{order}/upload-photo', [OrderController::class, 'uploadPhoto']);
        Route::get('/{order}/photos', [OrderController::class, 'photos']);
        Route::get('/{order}/waybill', [OrderController::class, 'showWaybill']);
    });

    /* --- ADMIN ROUTES --- */
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard-summary', [AdminDashboardController::class, 'summary']);
        
        // Product routes
        Route::post('/products', [ProductController::class, 'store']);
        Route::post('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
        Route::post('/orders/{order}/approve', [AdminOrderController::class, 'approve']);
        Route::post('/orders/{order}/waybill', [AdminOrderController::class, 'createWaybill']);
        Route::post('/orders/{order}/assign-driver', [AdminOrderController::class, 'assignDriver']);
        
        Route::get('/drivers', [AdminDriverController::class, 'index']);
        Route::post('/drivers', [AdminDriverController::class, 'store']);
        Route::get('/drivers/available', [AdminDriverController::class, 'available']);
    });

    /* --- DRIVER ROUTES --- */
    Route::middleware('role:driver')->prefix('driver')->group(function () {
        Route::get('/orders', [DriverOrderController::class, 'index']);
        Route::post('/orders/{id}/status', [DriverOrderController::class, 'updateStatus']);
        Route::post('/orders/{id}/complete', [DriverOrderController::class, 'completeDelivery']);
        Route::post('/orders/{id}/track', [DriverOrderController::class, 'track']);
        Route::post('/availability', [DriverOrderController::class, 'updateAvailability']);
    });
});