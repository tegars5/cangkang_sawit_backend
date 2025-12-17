<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\DriverOrderController;
use App\Http\Controllers\Api\WaybillController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/payment/tripay/callback', [PaymentController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('products', ProductController::class);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::get('/orders/{order}/tracking', [OrderController::class, 'tracking']);

    Route::post('/orders/{order}/pay', [PaymentController::class, 'pay']);

    // Admin Waybill Management
    Route::post('/admin/orders/{order}/waybill', [AdminOrderController::class, 'createWaybill']);
    Route::post('/admin/orders/{order}/assign-driver', [AdminOrderController::class, 'assignDriver']);

    // Waybill Viewing (Admin or Order Owner)
    Route::get('/orders/{order}/waybill', [OrderController::class, 'showWaybill']);
    
    // Optional: PDF Download (uncomment after installing dompdf)
    Route::get('/orders/{order}/waybill/pdf', [WaybillController::class, 'downloadWaybillPdf']);

    Route::get('/driver/orders', [DriverOrderController::class, 'index']);
    Route::post('/driver/delivery-orders/{deliveryOrder}/status', [DriverOrderController::class, 'updateStatus']);
    Route::post('/driver/delivery-orders/{deliveryOrder}/track', [DriverOrderController::class, 'track']);
});
