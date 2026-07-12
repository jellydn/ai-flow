<?php

use App\Http\Controllers\RunController;
use App\Models\Launcher;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::get('/launchers', fn () => Launcher::query()->where('active', true)->get()->map(fn ($launcher) => ['id' => $launcher->slug, 'slug' => $launcher->slug, 'name' => $launcher->name, 'description' => $launcher->description, 'input_type' => $launcher->input_type]));
Route::post('/runs', [RunController::class, 'store'])->middleware('throttle:runs');
Route::get('/runs/{run}', [RunController::class, 'show']);
Route::get('/runs/{run}/stream', [RunController::class, 'stream']);
