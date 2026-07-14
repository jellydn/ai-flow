<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRunRequest;
use App\Http\Resources\RunResource;
use App\Jobs\ExecuteLauncherJob;
use App\Models\Launcher;
use App\Models\ProviderCredential;
use App\Models\Run;
use App\Services\RunStreamer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RunController extends Controller
{
    public function __construct(
        private RunStreamer $streamer,
    ) {}

    public function store(StoreRunRequest $request): JsonResponse
    {
        $launcher = Launcher::where('slug', $request->validated('launcher'))->where('active', true)->firstOrFail();
        $input = ['source_url' => $request->validated('source_url')];

        $providerId = $request->validated('provider.id');
        $providerCredentialId = $request->validated('provider_credential_id');

        // If a saved credential is selected, snapshot its provider onto the run.
        $provider = $providerId;
        if ($providerCredentialId) {
            $credential = ProviderCredential::find($providerCredentialId);
            $provider = $credential->provider;
        }

        $run = $launcher->runs()->create([
            'user_id' => $request->user()?->id,
            'provider_credential_id' => $providerCredentialId,
            'provider' => $provider,
            'source_url' => $input['source_url'],
            'input' => $input,
            'status' => 'queued',
            'progress' => [],
        ]);

        ExecuteLauncherJob::dispatch(
            $run->id,
            $provider,
            $request->validated('provider.api_key'),
            $providerCredentialId,
        );

        return response()->json(['id' => $run->id, 'status' => 'queued', 'message' => 'Workflow started'], 202);
    }

    /**
     * Return recent completed public runs (user_id = null) for the home page.
     * Returns a lightweight summary — no full result, just repo/risk/findings count.
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

        $summary = $runs->map(function (Run $run): array {
            $sourceUrl = $run->source_url ?? '';
            preg_match('#github\.com/([^/]+/[^/]+)#i', $sourceUrl, $m);
            $repo = $m[1] ?? null;

            $type = match (true) {
                str_contains($sourceUrl, '/pull/') => 'Pull request',
                str_contains($sourceUrl, '/issues/') => 'Issue',
                default => 'Repository',
            };

            $result = $run->result ?? [];
            $findings = isset($result['findings']) ? count($result['findings']) : 0;
            $risk = $result['risk'] ?? '—';

            $durationSeconds = null;
            if ($run->started_at && $run->completed_at) {
                $durationSeconds = (int) $run->started_at->diffInSeconds($run->completed_at);
            }

            return [
                'id' => $run->id,
                'repo' => $repo,
                'type' => $type,
                'launcher_slug' => $run->launcher?->slug,
                'launcher_name' => $run->launcher?->name,
                'risk' => $risk,
                'findings_count' => $findings,
                'has_verification_steps' => ! empty($result['verification_steps']),
                'duration_seconds' => $durationSeconds,
                'completed_at' => $run->completed_at?->toIso8601String(),
            ];
        })->values();

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

        return response()->eventStream(function () use ($run) {
            yield from $this->streamer->stream($run);
        }, ['X-Accel-Buffering' => 'no', 'Cache-Control' => 'no-cache']);
    }
}
