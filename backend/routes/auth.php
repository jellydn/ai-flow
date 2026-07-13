<?php

use App\Http\Controllers\Auth\MagicLinkController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/magic-link', [MagicLinkController::class, 'request']);
Route::get('/auth/magic-link/{token}', [MagicLinkController::class, 'verify'])->name('auth.magic-link.verify');
Route::post('/auth/logout', [MagicLinkController::class, 'logout']);
