<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\GlobalCatalogController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PaymentTermController;
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
    Route::get('/relations/suppliers/payment-terms', [RelationController::class, 'paymentTerms']);

    Route::prefix('global-catalogs')->group(function () {
        Route::get('/{catalog}', [GlobalCatalogController::class, 'index']);
        Route::post('/{catalog}', [GlobalCatalogController::class, 'store']);
        Route::put('/{catalog}/{id}', [GlobalCatalogController::class, 'update'])->whereNumber('id');
        Route::delete('/{catalog}/{id}', [GlobalCatalogController::class, 'destroy'])->whereNumber('id');
    });

    Route::prefix('settings')->group(function () {
        Route::get('/currencies', [CurrencyController::class, 'index']);
        Route::post('/currencies', [CurrencyController::class, 'store']);
        Route::put('/currencies/{id}', [CurrencyController::class, 'update'])->whereNumber('id');
        Route::delete('/currencies/{id}', [CurrencyController::class, 'destroy'])->whereNumber('id');

        Route::get('/payment-terms', [PaymentTermController::class, 'index']);
        Route::post('/payment-terms', [PaymentTermController::class, 'store']);
        Route::put('/payment-terms/{id}', [PaymentTermController::class, 'update'])->whereNumber('id');
        Route::delete('/payment-terms/{id}', [PaymentTermController::class, 'destroy'])->whereNumber('id');

        Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
        Route::post('/payment-methods', [PaymentMethodController::class, 'store']);
        Route::put('/payment-methods/{id}', [PaymentMethodController::class, 'update'])->whereNumber('id');
        Route::delete('/payment-methods/{id}', [PaymentMethodController::class, 'destroy'])->whereNumber('id');

        Route::get('/document-types', [DocumentTypeController::class, 'index']);
        Route::post('/document-types', [DocumentTypeController::class, 'store']);
        Route::put('/document-types/{id}', [DocumentTypeController::class, 'update'])->whereNumber('id');
        Route::delete('/document-types/{id}', [DocumentTypeController::class, 'destroy'])->whereNumber('id');
    });

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
        ->only(['index', 'show', 'store', 'update', 'destroy']);
});
