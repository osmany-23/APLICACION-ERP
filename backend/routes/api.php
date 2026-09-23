<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashMovementController;
use App\Http\Controllers\CashMovementReasonController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CashSessionController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebitNoteController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\GeneralSettingsController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PaymentTermController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PosPinController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\ProductCatalogController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RelationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\TerminalController;
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

// Publica a proposito: un <img src="..."> no puede mandar el header
// Authorization Bearer que usa el resto de la API. El UUID es
// impredecible, ver el comentario en ImageController::show().
Route::get('/images/{uuid}', [ImageController::class, 'show'])
    ->where('uuid', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');

Route::middleware('erp.auth')->group(function () {
    Route::get('/dashboard/analytics', [DashboardController::class, 'analytics']);

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

        Route::get('/price-types', [PriceListController::class, 'index']);
        Route::post('/price-types', [PriceListController::class, 'store']);
        Route::put('/price-types/{id}', [PriceListController::class, 'update'])->whereNumber('id');
        Route::patch('/price-types/{id}/status', [PriceListController::class, 'updateStatus'])->whereNumber('id');
        Route::delete('/price-types/{id}', [PriceListController::class, 'destroy'])->whereNumber('id');
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
            Route::post('/{id}/image', 'uploadImage')->whereNumber('id');
            Route::delete('/{id}/image', 'deleteImage')->whereNumber('id');
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
    Route::post('/products/{product}/images', [ProductController::class, 'storeImage'])
        ->whereNumber('product');
    Route::delete('/products/{product}/images/{image}', [ProductController::class, 'destroyImage'])
        ->whereNumber(['product', 'image']);
    Route::post('/products/{product}/images/{image}/primary', [ProductController::class, 'setPrimaryImage'])
        ->whereNumber(['product', 'image']);

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
            Route::get('/exchange-rate', 'exchangeRate');
            Route::get('/receipt-config', 'receiptConfig');
            Route::get('/{sale}', 'show')->whereNumber('sale');
            Route::post('/', 'store');
            Route::put('/{sale}', 'update')->whereNumber('sale');
            Route::post('/{sale}/confirm', 'confirm')->whereNumber('sale');
            Route::post('/{sale}/payments', 'registerPayment')->whereNumber('sale');
            Route::post('/{sale}/cancel', 'cancel')->whereNumber('sale');
        });

    Route::post('/sales/{sale}/credit-notes', [CreditNoteController::class, 'store'])->whereNumber('sale');
    Route::post('/sales/{sale}/debit-notes', [DebitNoteController::class, 'store'])->whereNumber('sale');

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

    // Modulo de Apertura de Caja (POS).
    Route::prefix('terminals')
        ->controller(TerminalController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/checkin', 'checkin');
            Route::put('/{id}', 'update')->whereNumber('id');
            Route::delete('/{id}', 'destroy')->whereNumber('id');
        });

    Route::prefix('cash-registers')
        ->controller(CashRegisterController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::get('/{id}', 'show')->whereNumber('id');
            Route::post('/', 'store');
            Route::put('/{id}', 'update')->whereNumber('id');
            Route::patch('/{id}/status', 'updateStatus')->whereNumber('id');
            Route::delete('/{id}', 'destroy')->whereNumber('id');
        });

    Route::prefix('cash-movement-reasons')
        ->controller(CashMovementReasonController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{id}', 'update')->whereNumber('id');
            Route::delete('/{id}', 'destroy')->whereNumber('id');
        });

    Route::prefix('cash-sessions')
        ->controller(CashSessionController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::get('/current', 'current');
            Route::get('/monitor', 'monitor');
            Route::post('/open', 'open');
            Route::get('/{id}', 'show')->whereNumber('id');
            Route::get('/{id}/summary', 'summary')->whereNumber('id');
            Route::post('/{id}/close', 'close')->whereNumber('id');
            Route::post('/{id}/count', 'count')->whereNumber('id');
        });

    Route::prefix('cash-movements')
        ->controller(CashMovementController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::post('/{id}/authorize', 'authorize')->whereNumber('id');
            Route::post('/{id}/reject', 'reject')->whereNumber('id');
            Route::post('/{id}/cancel', 'cancel')->whereNumber('id');
        });
});
