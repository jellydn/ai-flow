<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertLauncherPromptRequest;
use App\Models\Launcher;
use App\Models\LauncherPromptOverride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LauncherPromptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $launchers = Launcher::query()
            ->where('active', true)
            ->orderBy('slug')
            ->get();

        $overrides = LauncherPromptOverride::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('launcher_id');

        $data = $launchers->map(function (Launcher $launcher) use ($overrides): array {
            $override = $overrides->get($launcher->id);

            return [
                'slug' => $launcher->slug,
                'name' => $launcher->name,
                'default_prompt_template' => $launcher->prompt_template,
                'override_prompt_template' => $override?->prompt_template,
                'uses_override' => $override !== null,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function update(UpsertLauncherPromptRequest $request, string $slug): JsonResponse
    {
        $launcher = Launcher::query()
            ->where('slug', $slug)
            ->where('active', true)
            ->firstOrFail();

        LauncherPromptOverride::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'launcher_id' => $launcher->id,
            ],
            ['prompt_template' => $request->validated('prompt_template')],
        );

        return response()->json(['message' => 'Workflow prompt saved.']);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $launcher = Launcher::query()
            ->where('slug', $slug)
            ->where('active', true)
            ->firstOrFail();

        LauncherPromptOverride::query()
            ->where('user_id', $request->user()->id)
            ->where('launcher_id', $launcher->id)
            ->delete();

        return response()->json(['message' => 'Workflow prompt reset to default.']);
    }
}
