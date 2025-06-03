<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuickBooksController;
use App\Http\Controllers\OrderFulfillmentController;

// Default Route
Route::get('/', function () {
    return view('welcome');
});

// QuickBooks Routes
Route::get('/quickbooks/redirect', [QuickBooksController::class, 'redirectToQuickBooks']);
Route::get('/quickbooks/callback', [QuickBooksController::class, 'handleQuickBooksCallback']);
Route::get('/quickbooks/{realmId}/refresh-token', [QuickBooksController::class, 'refreshQuickBooksAccessToken']);

Route::get('/admin/fulfillment', [OrderFulfillmentController::class, 'index'])->name('fulfillment.index');
Route::post('/admin/fulfillment/run', [OrderFulfillmentController::class, 'run'])->name('fulfillment.run');

