<?php

namespace App\Jobs;

use App\Contracts\AIProviderInterface;
use App\Contracts\RunExecutorInterface;
use App\Events\RunProgressed;
use App\Models\Run;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExecuteLauncherJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public string $runId,
        private ?string $provider = null,
        private ?string $apiKey = null,
    ) {}

    public function handle(RunExecutorInterface $executor): void
    {
        $run = Run::with('launcher')->findOrFail($this->runId);

        if ($this->provider !== null && ! in_array($this->provider, config('services.openai.providers'), true)) {
            $this->failRun($run, 'Unsupported AI provider.');

            return;
        }

        $this->configureProvider($this->provider);

        try {
            $ai = app()->make(AIProviderInterface::class, ['apiKey' => $this->apiKey]);
        } catch (Throwable $e) {
            $this->failRun($run, 'Run failed unexpectedly.', $e);

            return;
        }

        $executor->execute($run, $ai);
    }

    private function configureProvider(?string $providerId): void
    {
        if ($providerId === 'openrouter') {
            config([
                'services.openai.base_url' => config('services.openai.openrouter_base_url'),
                'services.openai.model' => config('services.openai.openrouter_model'),
            ]);
            if ($this->apiKey === null) {
                config([
                    'services.openai.key' => config('services.openai.openrouter_key') ?: config('services.openai.key'),
                ]);
            }

            return;
        }

        if ($providerId === 'openai') {
            config([
                'services.openai.base_url' => config('services.openai.openai_base_url'),
                'services.openai.model' => env('AI_MODEL') ?: env('OPENAI_MODEL', 'gpt-4o-mini'),
            ]);
            if ($this->apiKey === null && env('OPENAI_API_KEY')) {
                config(['services.openai.key' => env('OPENAI_API_KEY')]);
            }
        }
    }

    private function failRun(Run $run, string $message, ?Throwable $e = null): void
    {
        $run->update([
            'status' => 'failed',
            'error' => $message,
            'source_context' => null,
            'completed_at' => now(),
        ]);
        Log::error('Launcher run failed during provider setup', [
            'run_id' => $run->id,
            'exception' => $e ? get_class($e) : null,
        ]);
        RunProgressed::dispatch($run->fresh());
    }
}
