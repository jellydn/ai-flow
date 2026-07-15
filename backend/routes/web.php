<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::view('/{path?}', 'app')
    ->where('path', '^(?!api|admin|up|build|storage|vendor|favicon|robots|sitemap|\.well-known).*$')
    ->name('app');
