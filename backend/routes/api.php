<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\LauncherPromptController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\ProviderCredentialController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\RunHistoryController;
use App\Http\Resources\UserResource;
use App\Models\Launcher;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));
// Named login route prevents the auth middleware from crashing when redirecting
// unauthenticated requests that lack an Accept: application/json header.
Route::get('/login', fn () => response()->json(['message' => 'Unauthenticated.'], 401))->name('login');

$launchersResponse = fn () => Launcher::query()->where('active', true)->get()->map(fn ($launcher) => ['id' => $launcher->slug, 'slug' => $launcher->slug, 'name' => $launcher->name, 'description' => $launcher->description, 'input_type' => $launcher->input_type]);
Route::get('/launchers', $launchersResponse);
Route::get('/flows', $launchersResponse);
Route::get('/providers', [ProviderController::class, 'index']);
// Session (`web`) so the SPA cookie session is visible:
// - POST: attach user_id + allow provider_credential_id ownership checks
// - GET show/stream: owner can authorize('view') on private (user-owned) runs
// Still public (no `auth`) for anonymous create/view of public runs.
// CSRF applies to mutating methods only; SPA sends X-XSRF-TOKEN on POST.
Route::post('/runs', [RunController::class, 'store'])->middleware(['web', 'throttle:runs']);
Route::get('/runs/recent', [RunController::class, 'recent']);
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
    Route::delete('/account', [AccountController::class, 'destroy']);
});
Route::post('/executions', [RunController::class, 'store'])->middleware(['web', 'throttle:runs']);
Route::get('/executions/{run}', [RunController::class, 'show'])->middleware('web');
Route::get('/executions/{run}/stream', [RunController::class, 'stream'])->middleware(['web', 'throttle:runs-stream']);
