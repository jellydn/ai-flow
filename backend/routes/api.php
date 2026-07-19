<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\GitHubWebhookController;
use App\Http\Controllers\LauncherController;
use App\Http\Controllers\LauncherPromptController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\ProviderCredentialController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\RunHistoryController;
use App\Http\Controllers\TrendingRepositoryController;
use App\Http\Controllers\UserLauncherController;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

// GitHub bot webhook endpoint — receives issue_comment events when users
// tag @ai-flow in a comment. No auth middleware; verified via HMAC-SHA256.
Route::post('/github/webhooks', GitHubWebhookController::class);
// Named login route prevents the auth middleware from crashing when redirecting
// unauthenticated requests that lack an Accept: application/json header.
Route::get('/login', fn () => response()->json(['message' => 'Unauthenticated.'], 401))->name('login');

// web middleware needed so request()->user() is available for custom-launcher
// listing and hidden-launcher filtering (reads session cookie).
Route::get('/launchers', [LauncherController::class, 'index'])->middleware('web');
Route::get('/flows', [LauncherController::class, 'index'])->middleware('web');
Route::get('/providers', [ProviderController::class, 'index']);
// Session (`web`) so the SPA cookie session is visible:
// - POST: attach user_id + allow provider_credential_id ownership checks
// - GET show/stream: owner can authorize('view') on private (user-owned) runs
// Still public (no `auth`) for anonymous create/view of public runs.
// CSRF applies to mutating methods only; SPA sends X-XSRF-TOKEN on POST.
Route::post('/runs', [RunController::class, 'store'])->middleware(['web', 'throttle:runs']);
Route::get('/runs/recent', [RunController::class, 'recent']);
Route::get('/trending-repositories', [TrendingRepositoryController::class, 'index']);
Route::get('/runs/{run}', [RunController::class, 'show'])->middleware('web');
Route::get('/runs/{run}/stream', [RunController::class, 'stream'])->middleware(['web', 'throttle:runs-stream']);
Route::middleware(['web', 'auth'])->prefix('user')->group(function () {
    Route::get('/', fn () => new UserResource(request()->user()));
    Route::get('/runs', [RunHistoryController::class, 'index']);
    Route::get('/runs/{run}', [RunHistoryController::class, 'show']);
    Route::post('/runs/{run}/retry', [RunHistoryController::class, 'retry']);
    Route::delete('/runs/{run}', [RunHistoryController::class, 'destroy']);
    Route::get('/provider-credentials', [ProviderCredentialController::class, 'index']);
    Route::post('/provider-credentials', [ProviderCredentialController::class, 'store']);
    Route::patch('/provider-credentials/{credential}', [ProviderCredentialController::class, 'update']);
    Route::delete('/provider-credentials/{credential}', [ProviderCredentialController::class, 'destroy']);
    Route::post('/provider-credentials/{credential}/verify', [ProviderCredentialController::class, 'verify'])->middleware('throttle:credentials');
    Route::post('/provider-credentials/{credential}/make-default', [ProviderCredentialController::class, 'makeDefault']);
    Route::get('/launcher-prompts', [LauncherPromptController::class, 'index']);
    Route::put('/launcher-prompts/{slug}', [LauncherPromptController::class, 'update']);
    Route::delete('/launcher-prompts/{slug}', [LauncherPromptController::class, 'destroy']);
    Route::get('/launchers', [UserLauncherController::class, 'index']);
    Route::post('/launchers', [UserLauncherController::class, 'store']);
    Route::put('/launchers/{userLauncher}', [UserLauncherController::class, 'update']);
    Route::delete('/launchers/{userLauncher}', [UserLauncherController::class, 'destroy']);
    Route::get('/hidden-launchers', [UserLauncherController::class, 'hidden']);
    Route::post('/hidden-launchers/{launcher:slug}', [UserLauncherController::class, 'hide']);
    Route::delete('/hidden-launchers/{launcher:slug}', [UserLauncherController::class, 'unhide']);
    Route::delete('/account', [AccountController::class, 'destroy']);
});

// Backward-compat aliases
Route::post('/executions', [RunController::class, 'store'])->middleware(['web', 'throttle:runs']);
Route::get('/executions/{run}', [RunController::class, 'show'])->middleware('web');
Route::get('/executions/{run}/stream', [RunController::class, 'stream'])->middleware(['web', 'throttle:runs-stream']);
