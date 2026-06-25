<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductCatalogController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RelationController;
use Illuminate\Support\Facades\Route;

Route::options('/{any}', fn () => response()->noContent())->where('any', '.*');

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'app' => config('app.name'),
]));

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('erp.auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('erp.auth')->group(function () {
    Route::prefix('catalogs/{catalog}')
        ->whereIn('catalog', ['brands', 'categories', 'subcategories', 'units'])
        ->controller(ProductCatalogController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{id}', 'update')->whereNumber('id');
            Route::delete('/{id}', 'destroy')->whereNumber('id');
        });

    Route::prefix('relations/{relation}')
        ->whereIn('relation', ['suppliers', 'warehouses', 'branches', 'companies'])
        ->controller(RelationController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{id}', 'update')->whereNumber('id');
            Route::delete('/{id}', 'destroy')->whereNumber('id');
        });

    Route::get('/products/{product}/movements', [ProductController::class, 'movements'])
        ->whereNumber('product');
    Route::apiResource('products', ProductController::class)
        ->only(['index', 'store', 'update', 'destroy']);
});
