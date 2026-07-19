<?php

namespace App\Http\Controllers;

use App\Models\Launcher;
use App\Models\UserHiddenLauncher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserHiddenLauncherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $hidden = UserHiddenLauncher::query()
            ->join('launchers', 'launchers.id', '=', 'user_hidden_launchers.launcher_id')
            ->where('user_hidden_launchers.user_id', $request->user()->id)
            ->pluck('launchers.slug');

        return response()->json(['data' => $hidden]);
    }

    public function store(Request $request, Launcher $launcher): JsonResponse
    {
        UserHiddenLauncher::firstOrCreate([
            'user_id' => $request->user()->id,
            'launcher_id' => $launcher->id,
        ]);

        return response()->json(['message' => 'Launcher hidden.'], 201);
    }

    public function destroy(Request $request, Launcher $launcher): JsonResponse
    {
        UserHiddenLauncher::query()
            ->where('user_id', $request->user()->id)
            ->where('launcher_id', $launcher->id)
            ->delete();

        return response()->json(['message' => 'Launcher unhidden.'], 200);
    }
}
