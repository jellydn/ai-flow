<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRunRequest;
use App\Http\Resources\RunResource;
use App\Jobs\ExecuteLauncherJob;
use App\Models\Launcher;
use App\Models\Run;
use App\Services\GitHubService;
use App\Services\LauncherPromptResolver;
use App\Services\LaunchParameters;
use App\Services\RecentRunSummary;
use App\Services\RunStreamer;
use App\Support\AiProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RunController extends Controller
{
    public function __construct(
        private RunStreamer $streamer,
        private LauncherPromptResolver $promptResolver,
        private AiProviderRegistry $providerRegistry,
        private GitHubService $gitHubService,
    ) {}

    public function store(StoreRunRequest $request): JsonResponse
    {
        $launcher = Launcher::where('slug', $request->validated('launcher'))->where('active', true)->firstOrFail();
        $input = ['source_url' => $request->validated('source_url')];

        $params = LaunchParameters::resolve(
            providerId: $request->validated('provider.id'),
            oneTimeApiKey: $request->validated('provider.api_key'),
            providerCredentialId: $request->validated('provider_credential_id'),
            requestedModel: $request->validated('provider.model') ?? $request->validated('model'),
            registry: $this->providerRegistry,
            allowCustom: $request->user() !== null,
        );

        $promptSnapshot = $this->promptResolver->effectivePrompt($launcher, $request->user());

        $repoSlug = null;
        $repoType = null;
        try {
            $ref = $this->gitHubService->parse($request->validated('source_url'));
            $repoSlug = "{$ref->owner}/{$ref->repo}";
            $repoType = $ref->type;
        } catch (InvalidArgumentException) {
            // Invalid URL — repo metadata stays null.
        }

        $run = $launcher->runs()->create([
            'user_id' => $request->user()?->id,
            'provider_credential_id' => $params->providerCredentialId,
            'provider' => $params->effectiveProvider,
            'model' => $params->resolvedModel,
            'source_url' => $input['source_url'],
            'repo_slug' => $repoSlug,
            'repo_type' => $repoType,
            'input' => $input,
            'prompt_snapshot' => $promptSnapshot,
            'status' => 'queued',
            'progress' => [],
        ]);

        ExecuteLauncherJob::dispatch(
            $run->id,
            $params->rawProviderId,
            $params->oneTimeApiKey,
            $params->providerCredentialId,
            $params->resolvedModel,
        );

        return response()->json(['id' => $run->id, 'status' => 'queued', 'message' => 'Workflow started'], 202);
    }

    /**
     * Return recent completed public runs (user_id = null) for the home page.
     * Returns a lightweight summary — no full result, just repo/risk/findings count.
     *
     * Index coverage: the composite index runs_status_user_completed_at_index
     * (status, user_id, completed_at) added in migration 2026_07_15_000001
     * covers the (status = 'completed', user_id IS NULL, completed_at DESC)
     * predicate below.
     */
    public function recent(): JsonResponse
    {
        $runs = Run::query()
            ->where('status', 'completed')
            ->whereNull('user_id')
            ->whereNotNull('result')
            ->with('launcher:id,slug,name')
            ->orderByDesc('completed_at')
            ->limit(6)
            ->get();

        $summary = $runs->map(fn (Run $run): array => RecentRunSummary::from($run))->values();

        return response()->json(['data' => $summary]);
    }

    public function show(Run $run): JsonResource
    {
        $this->authorize('view', $run);

        return new RunResource($run->load('launcher'));
    }

    public function stream(Run $run): StreamedResponse
    {
        $this->authorize('view', $run);

        // Release the session lock before the long-lived SSE loop so other
        // same-user requests (status polls, account APIs) are not blocked.
        if (request()->hasSession()) {
            request()->session()->save();
        }

        return response()->eventStream(function () use ($run) {
            yield from $this->streamer->stream($run);
        }, ['X-Accel-Buffering' => 'no', 'Cache-Control' => 'no-cache']);
    }
}
