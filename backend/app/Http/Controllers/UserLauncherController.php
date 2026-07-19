<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserLauncherRequest;
use App\Http\Requests\UpdateUserLauncherRequest;
use App\Http\Resources\UserLauncherResource;
use App\Models\UserLauncher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserLauncherController extends Controller
{
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

    public function destroy(Request $request, UserLauncher $userLauncher): JsonResponse
    {
        $this->authorize('delete', $userLauncher);

        $userLauncher->delete();

        return response()->json(['message' => 'Custom launcher deleted.'], 200);
    }
}
