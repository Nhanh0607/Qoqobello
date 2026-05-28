<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route cho reset password
Route::get('/reset-password/{token}', function ($token) {
    return response()->json([
        'token' => $token,
        'message' => 'Dùng token này để reset password qua API',
    ]);
})->name('password.reset');