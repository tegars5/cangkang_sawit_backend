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
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/payment/tripay/callback', [PaymentController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    /* --- PROFILE ROUTES --- */
    Route::prefix('profile')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\ProfileController::class, 'show']);
        Route::put('/', [App\Http\Controllers\Api\ProfileController::class, 'update']);
        Route::put('/password', [App\Http\Controllers\Api\ProfileController::class, 'changePassword']);
        Route::post('/photo', [App\Http\Controllers\Api\ProfileController::class, 'uploadPhoto']);
    });
    
    /* --- FCM TOKEN ROUTE --- */
    Route::post('/fcm-token', [App\Http\Controllers\Api\UserController::class, 'updateFcmToken']);

    /* --- PRODUCT ROUTES --- */
    // Publik (Bisa diakses siapa saja yang login)
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::get('/products/categories', [ProductController::class, 'getCategories']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    
    // Khusus Admin
    Route::middleware('role:admin')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::post('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    });

    /* --- ORDER ROUTES --- */
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{order}', [OrderController::class, 'show']);
        Route::post('/{order}/cancel', [OrderController::class, 'cancel']);
        Route::get('/{order}/tracking', [OrderController::class, 'tracking']);
        Route::post('/{order}/pay', [PaymentController::class, 'pay']);
        
        // Foto Order
        Route::post('/{order}/upload-photo', [OrderController::class, 'uploadPhoto']);
        Route::get('/{order}/photos', [OrderController::class, 'photos']);
        
        Route::get('/{order}/waybill', [OrderController::class, 'showWaybill']);
        Route::get('/{order}/waybill/pdf', [WaybillController::class, 'downloadWaybillPdf']);
        Route::get('/{order}/distance', [DistanceController::class, 'orderDistance']);
        Route::get('/{order}/driver-distance', [DistanceController::class, 'driverDistance']);
    });

    /* --- ADMIN ROUTES --- */
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard-summary', [AdminDashboardController::class, 'summary']);
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::post('/orders/{order}/approve', [AdminOrderController::class, 'approve']);
        Route::post('/orders/{order}/waybill', [AdminOrderController::class, 'createWaybill']);
        Route::post('/orders/{order}/assign-driver', [AdminOrderController::class, 'assignDriver']);
        
        // Driver management
        Route::get('/drivers', [App\Http\Controllers\Api\AdminDriverController::class, 'index']);
        Route::get('/drivers/available', [App\Http\Controllers\Api\AdminDriverController::class, 'available']);
    });

    /* --- DRIVER ROUTES --- */
    Route::middleware('role:driver')->prefix('driver')->group(function () {
        Route::get('/orders', [DriverOrderController::class, 'index']);
        Route::post('/delivery-orders/{deliveryOrder}/status', [DriverOrderController::class, 'updateStatus']);
        Route::post('/delivery-orders/{deliveryOrder}/track', [DriverOrderController::class, 'track']);
        Route::post('/availability', [DriverOrderController::class, 'updateAvailability']);
    });
});