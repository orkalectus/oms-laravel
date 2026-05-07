<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ShippingController;
use Illuminate\Support\Facades\Route;



Route::prefix('v1')->group(function () {

    // ===== Public Routes =====
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // Products (public - no auth required)
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/search', [ProductController::class, 'search']);
        Route::get('/categories', [ProductController::class, 'categories']);
        Route::get('/{productId}', [ProductController::class, 'show']);
    });

    // Shipping public endpoints
    Route::prefix('shipping')->group(function () {
        Route::get('/provinces', [ShippingController::class, 'provinces']);
        Route::get('/cities', [ShippingController::class, 'cities']);
        Route::post('/calculate', [ShippingController::class, 'calculate']);
    });

    // Payment webhook (no auth - called by gateway)
    Route::post('/webhooks/payment', [PaymentController::class, 'webhook'])
        ->name('webhooks.payment');

    // ===== Protected Routes (requires authentication) =====
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/profile', [AuthController::class, 'profile']);
            Route::put('/profile', [AuthController::class, 'updateProfile']);
        });

        // Orders
        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::post('/', [OrderController::class, 'store']);
            Route::get('/{orderId}', [OrderController::class, 'show']);
            Route::post('/{orderId}/cancel', [OrderController::class, 'cancel']);
            Route::get('/{orderId}/status-history', [OrderController::class, 'statusHistory']);

            // Payments for order
            Route::post('/{orderId}/payment/initiate', [PaymentController::class, 'initiate']);
            Route::get('/{orderId}/payment/status', [PaymentController::class, 'status']);

            // Shipping for order
            Route::get('/{orderId}/tracking', [ShippingController::class, 'track']);
        });

        // Admin routes (would normally have admin middleware)
        Route::prefix('admin')->group(function () {
            Route::put('/orders/{orderId}/status', [OrderController::class, 'updateStatus']);
            Route::post('/orders/{orderId}/ship', [ShippingController::class, 'ship']);
        });

        // Simulation routes (debug only)
        // Route::prefix('simulate')->middleware(
        //     fn($request, $next) => config('app.debug')
        //         ? $next($request)
        //         : response()->json(['message' => 'Not available in production'], 403)
        // )->group(function () {
        //     Route::post('/orders/{orderId}/payment/success', [PaymentController::class, 'simulateSuccess']);
        //     Route::post('/orders/{orderId}/payment/failure', [PaymentController::class, 'simulateFailure']);
        // });

        Route::prefix('simulate')->group(function () {
            // Tambahkan pengecekan manual di sini
            if (!config('app.debug')) {
                Route::any('{any}', function () {
                    return response()->json(['message' => 'Not available in production'], 403);
                })->where('any', '.*');
            }

            Route::post('/orders/{orderId}/payment/success', [PaymentController::class, 'simulateSuccess']);
            Route::post('/orders/{orderId}/payment/failure', [PaymentController::class, 'simulateFailure']);
        });
    });

    // Health check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'Order Management System',
            'version' => '1.0.0',
            'timestamp' => now()->toIso8601String(),
            'services' => [
                'database' => \Illuminate\Support\Facades\DB::connection()->getPdo() ? 'ok' : 'error',
                'cache' => \Illuminate\Support\Facades\Cache::store()->has('health') !== null ? 'ok' : 'error',
                'queue' => 'ok', // Would check queue in real scenario
            ],
        ]);
    });
});
