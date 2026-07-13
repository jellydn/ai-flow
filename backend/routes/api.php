<?php

use App\Http\Controllers\ProviderController;
use App\Http\Controllers\ProviderCredentialController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\RunHistoryController;
use App\Http\Resources\UserResource;
use App\Models\Launcher;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::get('/launchers', fn () => Launcher::query()->where('active', true)->get()->map(fn ($launcher) => ['id' => $launcher->slug, 'slug' => $launcher->slug, 'name' => $launcher->name, 'description' => $launcher->description, 'input_type' => $launcher->input_type]));
Route::get('/flows', fn () => Launcher::query()->where('active', true)->get()->map(fn ($launcher) => ['id' => $launcher->slug, 'slug' => $launcher->slug, 'name' => $launcher->name, 'description' => $launcher->description, 'input_type' => $launcher->input_type]));
Route::get('/providers', [ProviderController::class, 'index']);
Route::post('/runs', [RunController::class, 'store'])->middleware('throttle:runs');
Route::get('/runs/{run}', [RunController::class, 'show']);
Route::get('/runs/{run}/stream', [RunController::class, 'stream'])->middleware('throttle:runs-stream');
Route::middleware('auth')->prefix('user')->group(function () {
    Route::get('/', fn () => new UserResource(request()->user()));
    Route::get('/runs', [RunHistoryController::class, 'index']);
    Route::get('/runs/{run}', [RunHistoryController::class, 'show']);
    Route::post('/runs/{run}/retry', [RunHistoryController::class, 'retry']);
    Route::delete('/runs/{run}', [RunHistoryController::class, 'destroy']);
    Route::get('/provider-credentials', [ProviderCredentialController::class, 'index']);
    Route::post('/provider-credentials', [ProviderCredentialController::class, 'store']);
    Route::patch('/provider-credentials/{credential}', [ProviderCredentialController::class, 'update']);
    Route::delete('/provider-credentials/{credential}', [ProviderCredentialController::class, 'destroy']);
    Route::post('/provider-credentials/{credential}/verify', [ProviderCredentialController::class, 'verify']);
    Route::post('/provider-credentials/{credential}/make-default', [ProviderCredentialController::class, 'makeDefault']);
});
Route::post('/executions', [RunController::class, 'store'])->middleware('throttle:runs');
Route::get('/executions/{run}', [RunController::class, 'show']);
Route::get('/executions/{run}/stream', [RunController::class, 'stream'])->middleware('throttle:runs-stream');
