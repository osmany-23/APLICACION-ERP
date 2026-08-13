<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GeneralSettingsController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PaymentTermController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PosPinController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ProductCatalogController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RelationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\UserController;
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
        Route::put('/me', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::get('/public/branding', [GeneralSettingsController::class, 'publicBranding']);

Route::middleware('erp.auth')->group(function () {
    Route::get('/relations/suppliers/payment-terms', [RelationController::class, 'paymentTerms']);

    Route::prefix('settings')->group(function () {
        Route::get('/general', [GeneralSettingsController::class, 'index']);
        Route::put('/general', [GeneralSettingsController::class, 'update']);
        Route::post('/general/logos', [GeneralSettingsController::class, 'uploadLogos']);
        Route::put('/general/currency', [GeneralSettingsController::class, 'updateCurrency']);
        Route::put('/general/headquarters', [GeneralSettingsController::class, 'updateHeadquarters']);
        Route::post('/general/exchange-rates', [GeneralSettingsController::class, 'storeExchangeRate']);
        Route::get('/general/exchange-rates/latest', [GeneralSettingsController::class, 'latestExchangeRate']);
        Route::post('/general/mail/test', [GeneralSettingsController::class, 'testMail']);
        Route::post('/general/backup/create', [GeneralSettingsController::class, 'createBackup']);
        Route::post('/general/backup/restore', [GeneralSettingsController::class, 'restoreBackup']);

        Route::get('/currencies', [CurrencyController::class, 'index']);
        Route::post('/currencies', [CurrencyController::class, 'store']);
        Route::put('/currencies/{id}', [CurrencyController::class, 'update'])->whereNumber('id');
        Route::patch('/currencies/{id}/status', [CurrencyController::class, 'updateStatus'])->whereNumber('id');
        Route::delete('/currencies/{id}', [CurrencyController::class, 'destroy'])->whereNumber('id');

        Route::get('/payment-terms', [PaymentTermController::class, 'index']);
        Route::post('/payment-terms', [PaymentTermController::class, 'store']);
        Route::put('/payment-terms/{id}', [PaymentTermController::class, 'update'])->whereNumber('id');
        Route::patch('/payment-terms/{id}/status', [PaymentTermController::class, 'updateStatus'])->whereNumber('id');
        Route::delete('/payment-terms/{id}', [PaymentTermController::class, 'destroy'])->whereNumber('id');

        Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
        Route::post('/payment-methods', [PaymentMethodController::class, 'store']);
        Route::put('/payment-methods/{id}', [PaymentMethodController::class, 'update'])->whereNumber('id');
        Route::patch('/payment-methods/{id}/status', [PaymentMethodController::class, 'updateStatus'])->whereNumber('id');
        Route::delete('/payment-methods/{id}', [PaymentMethodController::class, 'destroy'])->whereNumber('id');

        Route::get('/document-types', [DocumentTypeController::class, 'index']);
        Route::post('/document-types', [DocumentTypeController::class, 'store']);
        Route::put('/document-types/{id}', [DocumentTypeController::class, 'update'])->whereNumber('id');
        Route::patch('/document-types/{id}/status', [DocumentTypeController::class, 'updateStatus'])->whereNumber('id');
        Route::delete('/document-types/{id}', [DocumentTypeController::class, 'destroy'])->whereNumber('id');
    });

    Route::prefix('catalogs/{catalog}')
        ->whereIn('catalog', ['brands', 'categories', 'subcategories', 'units'])
        ->controller(ProductCatalogController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{id}', 'update')->whereNumber('id');
            Route::patch('/{id}/status', 'updateStatus')->whereNumber('id');
            Route::delete('/{id}', 'destroy')->whereNumber('id');
        });

    Route::prefix('relations/{relation}')
        ->whereIn('relation', ['suppliers', 'warehouses', 'branches', 'companies'])
        ->controller(RelationController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{id}', 'update')->whereNumber('id');
            Route::patch('/{id}/status', 'updateStatus')->whereNumber('id');
            Route::delete('/{id}', 'destroy')->whereNumber('id');
        });

    Route::get('/products/{product}/movements', [ProductController::class, 'movements'])
        ->whereNumber('product');
    Route::apiResource('products', ProductController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::patch('/products/{product}/status', [ProductController::class, 'updateStatus'])
        ->whereNumber('product');

    Route::prefix('customers')
        ->controller(CustomerController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::get('/{id}', 'show')->whereNumber('id');
            Route::post('/', 'store');
            Route::put('/{id}', 'update')->whereNumber('id');
            Route::patch('/{id}/status', 'updateStatus')->whereNumber('id');
            Route::delete('/{id}', 'destroy')->whereNumber('id');
        });

    Route::prefix('sales')
        ->controller(SaleController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::get('/{sale}', 'show')->whereNumber('sale');
            Route::post('/', 'store');
            Route::put('/{sale}', 'update')->whereNumber('sale');
            Route::post('/{sale}/confirm', 'confirm')->whereNumber('sale');
            Route::post('/{sale}/cancel', 'cancel')->whereNumber('sale');
        });

    Route::prefix('purchases')
        ->controller(PurchaseController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::get('/{purchase}', 'show')->whereNumber('purchase');
            Route::post('/', 'store');
            Route::put('/{purchase}', 'update')->whereNumber('purchase');
            Route::post('/{purchase}/confirm', 'confirm')->whereNumber('purchase');
            Route::post('/{purchase}/cancel', 'cancel')->whereNumber('purchase');
        });

    // Administracion: Usuarios / Roles / Permisos.
    Route::prefix('users')
        ->controller(UserController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::get('/{id}', 'show')->whereNumber('id');
            Route::post('/', 'store');
            Route::put('/{id}', 'update')->whereNumber('id');
            Route::patch('/{id}/status', 'updateStatus')->whereNumber('id');
            Route::post('/{id}/reset-password', 'resetPassword')->whereNumber('id');
            Route::post('/{id}/pin', 'generatePin')->whereNumber('id');
            Route::delete('/{id}/pin', 'revokePin')->whereNumber('id');
        });

    Route::prefix('roles')
        ->controller(RoleController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::get('/{id}', 'show')->whereNumber('id');
            Route::post('/', 'store');
            Route::put('/{id}', 'update')->whereNumber('id');
            Route::put('/{id}/permissions', 'syncPermissions')->whereNumber('id');
            Route::delete('/{id}', 'destroy')->whereNumber('id');
        });

    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::get('/countries', [CountryController::class, 'index']);

    // Empleados, cargos y departamentos.
    Route::prefix('employees')
        ->controller(EmployeeController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::get('/{id}', 'show')->whereNumber('id');
            Route::post('/', 'store');
            Route::put('/{id}', 'update')->whereNumber('id');
            Route::delete('/{id}', 'destroy')->whereNumber('id');
        });

    Route::prefix('departments')
        ->controller(DepartmentController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{id}', 'update')->whereNumber('id');
            Route::patch('/{id}/status', 'updateStatus')->whereNumber('id');
            Route::delete('/{id}', 'destroy')->whereNumber('id');
        });

    Route::prefix('positions')
        ->controller(PositionController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{id}', 'update')->whereNumber('id');
            Route::patch('/{id}/status', 'updateStatus')->whereNumber('id');
            Route::delete('/{id}', 'destroy')->whereNumber('id');
        });

    Route::post('/pos/pin/resolve', [PosPinController::class, 'resolve']);
});
