<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\EmployeeController;
use App\Http\Controllers\Web\ExpenseController;
use App\Http\Controllers\Web\OnboardingController;
use App\Http\Controllers\Web\OrderController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\PurchaseController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\ShiftController;
use App\Http\Controllers\Web\StocktakeController;
use App\Http\Controllers\Web\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:15,1')->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'store.context'])->group(function (): void {
    // 60-Second Onboarding Wizard
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

    Route::middleware(['onboarding.completed'])->group(function (): void {
        Route::get('/dashboard', [ReportController::class, 'dashboard'])->name('dashboard');
        Route::view('/pos', 'pos.placeholder')->middleware('permission:pos.sell')->name('pos.index');

        // Smart Products & Native CSV Engine
        Route::get('/products/barcode-lookup', [ProductController::class, 'barcodeLookup'])->name('products.barcode-lookup');
        Route::middleware('permission:products.manage')->group(function (): void {
            Route::get('/products', [ProductController::class, 'index'])->name('products.index');
            Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('/products', [ProductController::class, 'store'])->name('products.store');
            Route::get('/products/export-csv', [ProductController::class, 'exportCsv'])->name('products.export-csv');
            Route::post('/products/import-csv', [ProductController::class, 'importCsv'])->name('products.import-csv');
            Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
            Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

            // Stocktake & Stock Card
            Route::get('/stocktake', [StocktakeController::class, 'index'])->name('stocktake.index');
            Route::post('/stocktake', [StocktakeController::class, 'store'])->name('stocktake.store');
            Route::get('/products/{product}/stock-card', [StocktakeController::class, 'stockCard'])->name('products.stock-card');
        });

        // Suppliers & Purchases
        Route::middleware('permission:suppliers.manage')->group(function (): void {
            Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
            Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
            Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
            Route::post('/suppliers/{supplier}/payments', [SupplierController::class, 'recordPayment'])->name('suppliers.payments.store');
            Route::get('/suppliers/{supplier}/statement', [SupplierController::class, 'statement'])->name('suppliers.statement');

            Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
            Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
            Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
            Route::post('/purchases/{purchase}/return', [PurchaseController::class, 'recordReturn'])->name('purchases.return');
        });

        // Employee Accounts, Advances & Salaries
        Route::middleware('permission:employees.manage')->group(function (): void {
            Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
            Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
            Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
            Route::post('/employees/{employee}/transactions', [EmployeeController::class, 'recordTransaction'])->name('employees.transactions.store');
            Route::post('/employees/accrue-salaries', [EmployeeController::class, 'accrueSalaries'])->name('employees.accrue-salaries');
            Route::get('/employees/{employee}/statement', [EmployeeController::class, 'statement'])->name('employees.statement');
        });

        // Customer Credit Ledger
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::post('/customers/{customer}/settlements', [CustomerController::class, 'recordSettlement'])->name('customers.settlements.store');
        Route::get('/customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement');

        // Immutable Orders & Returns (ZERO edit/delete routes!)
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/return', [OrderController::class, 'recordReturn'])->middleware('permission:pos.return')->name('orders.return');

        // Shifts & Z-Report
        Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
        Route::post('/shifts/open', [ShiftController::class, 'open'])->name('shifts.open');
        Route::get('/shifts/{shift}', [ShiftController::class, 'show'])->name('shifts.show');
        Route::post('/shifts/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');
        Route::post('/shifts/{shift}/transfer-shortage', [ShiftController::class, 'transferShortage'])->middleware('permission:employees.manage')->name('shifts.transfer-shortage');

        // Expenses
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');

        // Financial Profit Reports
        Route::get('/reports', [ReportController::class, 'index'])->middleware('permission:reports.view_profit')->name('reports.index');

        // Store Settings, Exchange Rate, Users & Terminals
        Route::middleware('permission:settings.manage')->group(function (): void {
            Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::post('/settings/exchange-rate', [SettingsController::class, 'updateExchangeRate'])->name('settings.exchange-rate');
            Route::post('/settings/users', [SettingsController::class, 'storeUser'])->name('settings.users.store');
            Route::put('/settings/users/{user}', [SettingsController::class, 'updateUser'])->name('settings.users.update');
            Route::post('/settings/terminals', [SettingsController::class, 'storeTerminal'])->name('settings.terminals.store');
            Route::post('/settings/terminals/{terminal}/revoke', [SettingsController::class, 'revokeTerminal'])->name('settings.terminals.revoke');
        });
    });
});
