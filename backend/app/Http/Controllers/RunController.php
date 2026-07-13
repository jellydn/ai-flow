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
