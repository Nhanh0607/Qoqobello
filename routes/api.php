<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuctionController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PinController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\AuctionController as AdminAuctionController;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::prefix('v1/auth')->group(function () {
    Route::post('/register',        [AuthController::class, 'register']);
    Route::post('/login',           [AuthController::class, 'login']);
    Route::get('/google/redirect',  [AuthController::class, 'googleRedirect']);
    Route::get('/google/callback',  [AuthController::class, 'googleCallback']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password',  [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// PIN routes
Route::prefix('v1/pin')->group(function () {
    Route::post('/login', [PinController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/setup',  [PinController::class, 'setup']);
        Route::delete('/',     [PinController::class, 'destroy']);
        Route::post('/unlock', [PinController::class, 'unlock']);
    });
});

// User routes
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Profile
    Route::get('/profile',                 [ProfileController::class, 'index']);
    Route::put('/profile',                 [ProfileController::class, 'update']);
    Route::put('/profile/change-password', [ProfileController::class, 'changePassword']);

    // Auctions
    Route::get('/auctions/by-date',            [AuctionController::class, 'byDate']);
    Route::get('/auctions',                    [AuctionController::class, 'index']);
    Route::get('/auctions/{auction}',          [AuctionController::class, 'show']);
    Route::post('/auctions/{auction}/join',    [AuctionController::class, 'join']);
    Route::post('/auctions/{auction}/bid',     [AuctionController::class, 'bid']);
    Route::post('/auctions/{auction}/buy-now', [AuctionController::class, 'buyNow']);
    Route::post('/auctions/{auction}/pay',     [AuctionController::class, 'pay']);

    // History
    Route::get('/history', [HistoryController::class, 'index']);

    // Wallet
    Route::get('/wallet',              [WalletController::class, 'index']);
    Route::post('/wallet/buy',         [WalletController::class, 'buy']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
});

// Admin routes
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'is_admin'])->group(function () {
    Route::get('/products',  [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/auctions',  [AdminAuctionController::class, 'index']);
    Route::post('/auctions', [AdminAuctionController::class, 'store']);
});