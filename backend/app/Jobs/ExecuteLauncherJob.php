<?php

namespace App\Jobs;

use App\Contracts\AIProviderInterface;
use App\Contracts\RunExecutorInterface;
use App\Events\RunProgressed;
use App\Models\Run;
use App\Services\AnthropicProvider;
use App\Services\GeminiProvider;
use App\Services\OpenAIProvider;
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

        try {
            $ai = app()->make($this->resolveProviderClass(), ['apiKey' => $this->apiKey]);
        } catch (Throwable $e) {
            $this->failRun($run, 'Run failed unexpectedly.', $e);

            return;
        }

        $executor->execute($run, $ai);
    }

    /**
     * @return class-string<AIProviderInterface>
     */
    private function resolveProviderClass(): string
    {
        return match ($this->provider) {
            'anthropic' => AnthropicProvider::class,
            'gemini' => GeminiProvider::class,
            default => OpenAIProvider::class,
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
