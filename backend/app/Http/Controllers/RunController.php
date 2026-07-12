<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRunRequest;
use App\Http\Resources\RunResource;
use App\Jobs\ExecuteLauncherJob;
use App\Models\Launcher;
use App\Models\Run;
use App\Support\AiProviders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\StreamedEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RunController extends Controller
{
    public function store(StoreRunRequest $request): JsonResponse
    {
        $launcher = Launcher::where('slug', $request->validated('launcher'))->where('active', true)->firstOrFail();
        $input = ['source_url' => $request->validated('source_url')];
        $run = $launcher->runs()->create([
            'source_url' => $input['source_url'],
            'input' => $input,
            'status' => 'queued',
            'progress' => [],
        ]);
        $providerId = $request->validated('provider.id') ?? AiProviders::OPENAI;

        ExecuteLauncherJob::dispatch(
            $run->id,
            $providerId,
            $request->validated('provider.api_key'),
        );

        return response()->json(['id' => $run->id, 'status' => 'queued', 'message' => 'Workflow started'], 202);
    }

    public function show(Run $run): JsonResource
    {
        return new RunResource($run->load('launcher'));
    }

    public function stream(Run $run): StreamedResponse
    {
        return response()->eventStream(function () use ($run) {
            $last = null;
            $deadline = microtime(true) + 55;
            while (microtime(true) < $deadline && ! connection_aborted()) {
                $run->refresh();
                $snapshot = (new RunResource($run->loadMissing('launcher')))->resolve();
                $encoded = json_encode($snapshot);
                if ($encoded !== $last) {
                    yield new StreamedEvent(event: 'progress', data: $encoded);
                    $last = $encoded;
                }
                if (in_array($run->status, ['completed', 'failed'], true)) {
                    yield new StreamedEvent(event: $run->status, data: $encoded);
                    break;
                }
                usleep(1_000_000);
            }
        }, ['X-Accel-Buffering' => 'no', 'Cache-Control' => 'no-cache']);
    }
}
