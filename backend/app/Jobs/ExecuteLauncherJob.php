<?php

namespace App\Jobs;

use App\Contracts\RunExecutorInterface;
use App\Events\RunProgressed;
use App\Models\Run;
use App\Support\AiProviderRegistry;
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

        $providerId = $this->provider ?? 'openai';

        try {
            $registry = app(AiProviderRegistry::class);

            if (! $registry->has($providerId)) {
                $this->failRun($run, 'Unsupported AI provider.');

                return;
            }

            $ai = $registry->get($providerId, $this->resolveApiKey($providerId));
        } catch (Throwable $e) {
            $this->failRun($run, 'Run failed unexpectedly.', $e);

            return;
        }

        $executor->execute($run, $ai);
    }

    /**
     * Resolve the API key for the given provider.
     *
     * If a one-time key was provided to the job, use it.
     * Otherwise fall back to the server-configured key for the provider.
     */
    private function resolveApiKey(string $providerId): ?string
    {
        if ($this->apiKey !== null) {
            return $this->apiKey;
        }

        return match ($providerId) {
            'openrouter' => config('services.openai.openrouter_key') ?: config('services.openai.key'),
            'anthropic' => config('services.anthropic.key'),
            'gemini' => config('services.gemini.key'),
            default => config('services.openai.key'),
        };
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
