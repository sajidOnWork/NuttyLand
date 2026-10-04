<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogueController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Staff\StaffOrderController;
use App\Http\Controllers\Staff\StaffSalesController;
use App\Http\Controllers\Staff\StaffStockController;
use App\Http\Controllers\Staff\StaffHomeController;
use Illuminate\Support\Facades\Route;

/*
| Customer website (e-commerce + Click & Collect)
*/
Route::get('/', [CatalogueController::class, 'home'])->name('home');
Route::get('/shop', [CatalogueController::class, 'index'])->name('shop');
Route::get('/products/{product:slug}', [CatalogueController::class, 'show'])->name('products.show');
Route::get('/markets', [CatalogueController::class, 'markets'])->name('markets');

Route::get('/cart', [CartController::class, 'show'])->name('cart');
Route::post('/cart', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/{variant}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{variant}', [CartController::class, 'remove'])->name('cart.remove');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/orders/{order}/pay', [CheckoutController::class, 'payment'])->name('orders.pay');
    Route::post('/orders/{order}/pay', [CheckoutController::class, 'processPayment'])->name('orders.pay.process');

    Route::get('/account', [OrderController::class, 'index'])->name('account');
    Route::put('/account', [OrderController::class, 'updateProfile'])->name('account.update');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});

/*
| Market staff screens (mobile-first). Owners can use them too.
*/
Route::middleware(['auth', 'staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/', [StaffHomeController::class, 'index'])->name('home');
    Route::post('/alerts/read', [StaffHomeController::class, 'markAlertsRead'])->name('alerts.read');

    Route::get('/sell', [StaffSalesController::class, 'sell'])->name('sell');
    Route::get('/api/catalogue', [StaffSalesController::class, 'catalogue'])->name('api.catalogue');
    Route::post('/api/sales', [StaffSalesController::class, 'sync'])->name('api.sales');
    Route::get('/sales', [StaffSalesController::class, 'index'])->name('sales');

    Route::get('/orders', [StaffOrderController::class, 'index'])->name('orders');
    Route::get('/orders/{order}', [StaffOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/status', [StaffOrderController::class, 'updateStatus'])->name('orders.status');

    Route::get('/stock', [StaffStockController::class, 'index'])->name('stock');
    Route::post('/stock/transfer', [StaffStockController::class, 'transfer'])->name('stock.transfer');
    Route::post('/stock/adjust', [StaffStockController::class, 'adjust'])->name('stock.adjust');
});

/*
| Report CSV exports (linked from the admin Reports page)
*/
Route::get('/admin-reports/export/{type}', \App\Http\Controllers\ReportExportController::class)
    ->middleware('auth')->whereIn('type', ['products', 'daily'])->name('reports.export');
