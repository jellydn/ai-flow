<?php

namespace App\Http\Controllers;

use App\Http\Resources\RunResource;
use App\Jobs\ExecuteLauncherJob;
use App\Models\Run;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RunHistoryController extends Controller
{
    /**
     * List the authenticated user's runs with optional filtering and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', Run::STATUSES)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, Closure $fail) use ($request) {
                    if ($request->filled(['date_from', 'date_to']) && strtotime((string) $value) < strtotime((string) $request->input('date_from'))) {
                        $fail('The '.$attribute.' must be a date after or equal to date_from.');
                    }
                },
            ],
            'launcher' => ['nullable', 'string'],
            'provider' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:500'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $runs = Run::query()
            ->where('user_id', $request->user()->id)
            ->with('launcher')
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('launcher'), fn ($q, $v) => $q->whereHas('launcher', fn ($l) => $l->where('slug', $v)))
            ->when($request->query('provider'), fn ($q, $v) => $q->where('provider', $v))
            ->when($request->query('date_from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->query('date_to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($request->query('search'), fn ($q, $v) => $q->where('source_url', 'like', '%'.$v.'%'))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return RunResource::collection($runs);
    }

    /**
     * Show a single run owned by the authenticated user.
     */
    public function show(Run $run): RunResource
    {
        $this->authorize('view', $run);

        return new RunResource($run->load('launcher'));
    }

    /**
     * Retry a completed or failed run.
     */
    public function retry(Run $run): JsonResponse
    {
        $this->authorize('retry', $run);

        $newRun = $run->replicate(['id', 'status', 'progress', 'result', 'error', 'source_context', 'started_at', 'completed_at', 'created_at', 'updated_at']);
        $newRun->status = 'queued';
        $newRun->progress = [];
        $newRun->result = null;
        $newRun->error = null;
        $newRun->source_context = null;
        $newRun->started_at = null;
        $newRun->completed_at = null;
        $newRun->save();

        ExecuteLauncherJob::dispatch($newRun->id, $newRun->provider);

        return response()->json(['id' => $newRun->id, 'status' => 'queued', 'message' => 'Run retried'], 202);
    }

    /**
     * Delete a run owned by the authenticated user.
     */
    public function destroy(Run $run): JsonResponse
    {
        $this->authorize('delete', $run);

        $run->delete();

        return response()->json(['message' => 'Run deleted.'], 200);
    }
}
