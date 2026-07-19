<?php

namespace App\Http\Controllers;

use App\Http\Resources\LauncherResource;
use App\Models\Launcher;
use App\Models\UserHiddenLauncher;
use App\Models\UserLauncher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LauncherController extends Controller
{
    /**
     * Unified launcher list: built-in active launchers + authenticated user's
     * custom launchers. Hidden built-in launchers are filtered out.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Launcher::query()->where('active', true);

        $user = $request->user();

        // ?include_hidden=true skips the hidden-launcher filter so the
        // visibility-settings section can always show every built-in.
        $includeHidden = (bool) $request->query('include_hidden');

        if ($user && ! $includeHidden) {
            $hiddenIds = UserHiddenLauncher::query()
                ->where('user_id', $user->id)
                ->pluck('launcher_id')
                ->toArray();

            if ($hiddenIds !== []) {
                $query->whereNotIn('id', $hiddenIds);
            }
        }

        $builtIn = $query->get()->map(fn (Launcher $launcher): array => [
            'slug' => $launcher->slug,
            'name' => $launcher->name,
            'description' => $launcher->description,
            'input_type' => $launcher->input_type,
            'is_custom' => false,
        ]);

        if ($user) {
            $custom = UserLauncher::query()
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn (UserLauncher $launcher): array => [
                    'slug' => $launcher->slug,
                    'name' => $launcher->name,
                    'description' => $launcher->description,
                    'input_type' => $launcher->input_type,
                    'is_custom' => true,
                ]);

            $builtIn = $builtIn->concat($custom);
        }

        return response()->json(LauncherResource::collection($builtIn->values())->resolve());
    }
}
