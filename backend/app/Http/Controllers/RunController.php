<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRunRequest;
use App\Http\Resources\RunResource;
use App\Jobs\ExecuteLauncherJob;
use App\Models\Launcher;
use App\Models\Run;
use App\Models\UserLauncher;
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
        $slug = $request->validated('launcher');
        $user = $request->user();
        $input = ['source_url' => $request->validated('source_url')];

        // Resolve launcher: built-in first, then user custom.
        $builtInLauncher = Launcher::where('slug', $slug)->where('active', true)->first();
        $userLauncher = null;
        $promptSnapshot = null;

        if ($builtInLauncher !== null) {
            $promptSnapshot = $this->promptResolver->effectivePrompt($builtInLauncher, $user);
        } else {
            $userLauncher = UserLauncher::where('slug', $slug)->where('user_id', $user?->id)->firstOrFail();
            $promptSnapshot = $userLauncher->prompt_template;
        }

        // launcher_id is NOT NULL (SQLite-compatible). Custom-launcher runs use a
        // placeholder built-in launcher_id so FK constraints stay intact. The real
        // launcher is resolved via user_launcher_id → launcherSource() at read time.
        // NOTE: if the placeholder launcher is ever deleted, cascadeOnDelete will
        // drop all custom-launcher runs referencing it. Built-in launchers are
        // seeded and not deleted in normal operation, so this is acceptable.
        $launcherId = $builtInLauncher?->id
            ?? Launcher::where('active', true)->value('id');

        $params = LaunchParameters::resolve(
            providerId: $request->validated('provider.id'),
            oneTimeApiKey: $request->validated('provider.api_key'),
            providerCredentialId: $request->validated('provider_credential_id'),
            requestedModel: $request->validated('provider.model') ?? $request->validated('model'),
            registry: $this->providerRegistry,
            allowCustom: $user !== null,
        );

        $repoSlug = null;
        $repoType = null;
        try {
            $ref = $this->gitHubService->parse($request->validated('source_url'));
            $repoSlug = "{$ref->owner}/{$ref->repo}";
            $repoType = $ref->type;
        } catch (InvalidArgumentException) {
            // Invalid URL — repo metadata stays null.
        }

        $isPublic = $user ? (bool) $request->validated('is_public') : true;

        $run = Run::create([
            'launcher_id' => $launcherId,
            'user_launcher_id' => $userLauncher?->id,
            'user_id' => $user?->id,
            'provider_credential_id' => $params->providerCredentialId,
            'provider' => $params->effectiveProvider,
            'model' => $params->resolvedModel,
            'source_url' => $input['source_url'],
            'repo_slug' => $repoSlug,
            'repo_type' => $repoType,
            'input' => $input,
            'prompt_snapshot' => $promptSnapshot,
            'is_public' => $isPublic,
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

        return new RunResource($run->load(['launcher', 'userLauncher']));
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
