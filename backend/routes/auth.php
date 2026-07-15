<?php

use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\PasswordAuthController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [PasswordAuthController::class, 'register'])->middleware('throttle:auth-register');
Route::post('/auth/login', [PasswordAuthController::class, 'login'])->middleware('throttle:auth-login');
Route::post('/auth/magic-link', [MagicLinkController::class, 'request'])->middleware('throttle:magic-link');
Route::get('/auth/magic-link/{token}', [MagicLinkController::class, 'verify'])->name('auth.magic-link.verify');
Route::post('/auth/logout', [MagicLinkController::class, 'logout']);
