<?php

namespace App\Http\Controllers;

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

        if ($user) {
            $hiddenIds = UserHiddenLauncher::query()
                ->where('user_id', $user->id)
                ->pluck('launcher_id')
                ->toArray();

            if ($hiddenIds !== []) {
                $query->whereNotIn('id', $hiddenIds);
            }
        }

        $builtIn = $query->get()->map(function (Launcher $launcher): array {
            $meta = launcherServerMeta($launcher->slug);

            return [
                'id' => $launcher->slug,
                'slug' => $launcher->slug,
                'name' => $launcher->name,
                'description' => $launcher->description,
                'input_type' => $launcher->input_type,
                'icon' => $meta['icon'],
                'tone' => $meta['tone'],
                'is_custom' => false,
            ];
        });

        if ($user) {
            $custom = UserLauncher::query()
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function (UserLauncher $launcher): array {
                    $meta = customLauncherMeta($launcher->slug);

                    return [
                        'id' => $launcher->slug,
                        'slug' => $launcher->slug,
                        'name' => $launcher->name,
                        'description' => $launcher->description,
                        'input_type' => $launcher->input_type,
                        'icon' => $meta['icon'],
                        'tone' => $meta['tone'],
                        'is_custom' => true,
                    ];
                });

            $builtIn = $builtIn->concat($custom);
        }

        return response()->json($builtIn->values());
    }
}
