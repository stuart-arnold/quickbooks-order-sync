<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuickBooksController;

// Default Route
Route::get('/', function () {
    return view('welcome');
});

// QuickBooks Routes
Route::get('/quickbooks/redirect', [QuickBooksController::class, 'redirectToQuickBooks']);
Route::get('/quickbooks/callback', [QuickBooksController::class, 'handleQuickBooksCallback']);
Route::get('/quickbooks/{realmId}/refresh-token', [QuickBooksController::class, 'refreshQuickBooksAccessToken']);

Route::get('/orders/{orderId}/process', [OrderController::class, 'processOrder']);

