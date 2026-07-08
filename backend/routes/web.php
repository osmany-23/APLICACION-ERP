<?php

use App\Http\Controllers\SupplierFormController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('erp.auth')->group(function () {
    Route::get('/suppliers', [SupplierFormController::class, 'index'])->name('suppliers.index');
    Route::post('/suppliers', [SupplierFormController::class, 'store'])->name('suppliers.store');
    Route::patch('/suppliers/{id}/status', [SupplierFormController::class, 'toggleStatus'])->name('suppliers.toggleStatus')->whereNumber('id');
    Route::get('/suppliers/{id}/edit', [SupplierFormController::class, 'edit'])->name('suppliers.edit')->whereNumber('id');
    Route::put('/suppliers/{id}', [SupplierFormController::class, 'update'])->name('suppliers.update')->whereNumber('id');
});
