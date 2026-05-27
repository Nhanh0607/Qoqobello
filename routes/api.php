<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuctionController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\AuctionController as AdminAuctionController;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::prefix('v1/auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
    Route::get('/google/redirect', [AuthController::class, 'googleRedirect']);
    Route::get('/google/callback', [AuthController::class, 'googleCallback']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// User routes
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Auctions
    Route::get('/auctions',                    [AuctionController::class, 'index']);
    Route::get('/auctions/{auction}',          [AuctionController::class, 'show']);
    Route::post('/auctions/{auction}/join',    [AuctionController::class, 'join']);
    Route::post('/auctions/{auction}/bid',     [AuctionController::class, 'bid']);
    Route::post('/auctions/{auction}/buy-now', [AuctionController::class, 'buyNow']);

    // History
    Route::get('/history', [HistoryController::class, 'index']);

    // Wallet
    Route::get('/wallet',                  [WalletController::class, 'index']);
    Route::post('/wallet/buy',             [WalletController::class, 'buy']);
    Route::get('/wallet/transactions',     [WalletController::class, 'transactions']);
});

// Admin routes
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'is_admin'])->group(function () {
    Route::get('/products',  [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/auctions',  [AdminAuctionController::class, 'index']);
    Route::post('/auctions', [AdminAuctionController::class, 'store']);
});