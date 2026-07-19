<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserLauncherRequest;
use App\Http\Requests\UpdateUserLauncherRequest;
use App\Http\Resources\UserLauncherResource;
use App\Models\Launcher;
use App\Models\UserHiddenLauncher;
use App\Models\UserLauncher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserLauncherController extends Controller
{
    /** Custom launcher CRUD */
    public function index(Request $request): AnonymousResourceCollection
    {
        $launchers = UserLauncher::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return UserLauncherResource::collection($launchers);
    }

    public function store(StoreUserLauncherRequest $request): JsonResponse
    {
        $launcher = UserLauncher::create([
            'user_id' => $request->user()->id,
            'slug' => $request->validated('slug'),
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'prompt_template' => $request->validated('prompt_template'),
            'input_type' => $request->validated('input_type'),
            'output_schema' => $request->validatedOutputSchema(),
        ]);

        return (new UserLauncherResource($launcher))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateUserLauncherRequest $request, UserLauncher $userLauncher): UserLauncherResource
    {
        $this->authorize('update', $userLauncher);

        $data = array_filter([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'prompt_template' => $request->validated('prompt_template'),
            'input_type' => $request->validated('input_type'),
            'output_schema' => $request->validatedOutputSchema(),
        ], fn ($v) => $v !== null);

        $userLauncher->update($data);

        return new UserLauncherResource($userLauncher->fresh());
    }

    public function destroy(UserLauncher $userLauncher): JsonResponse
    {
        $this->authorize('delete', $userLauncher);

        $userLauncher->delete();

        return response()->json(['message' => 'Custom launcher deleted.'], 200);
    }

    /** Built-in launcher visibility */
    public function hidden(Request $request): JsonResponse
    {
        $hidden = UserHiddenLauncher::query()
            ->join('launchers', 'launchers.id', '=', 'user_hidden_launchers.launcher_id')
            ->where('user_hidden_launchers.user_id', $request->user()->id)
            ->pluck('launchers.slug');

        return response()->json(['data' => $hidden]);
    }

    public function hide(Request $request, Launcher $launcher): JsonResponse
    {
        UserHiddenLauncher::firstOrCreate([
            'user_id' => $request->user()->id,
            'launcher_id' => $launcher->id,
        ]);

        return response()->json(['message' => 'Launcher hidden.'], 201);
    }

    public function unhide(Request $request, Launcher $launcher): JsonResponse
    {
        UserHiddenLauncher::query()
            ->where('user_id', $request->user()->id)
            ->where('launcher_id', $launcher->id)
            ->delete();

        return response()->json(['message' => 'Launcher unhidden.'], 200);
    }
}
