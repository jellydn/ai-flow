<?php

namespace App\Jobs;

use App\Models\ProviderCredential;
use App\Models\Run;
use App\Services\LaunchAiKeyResolver;
use App\Services\RunExecutor;
use App\Support\AiProviderRegistry;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
        private ?string $providerCredentialId = null,
        private ?string $model = null,
    ) {}

    public function handle(RunExecutor $executor): void
    {
        $run = Run::with('launcher')->findOrFail($this->runId);

        $providerId = $this->provider ?? 'openai';

        try {
            $registry = app(AiProviderRegistry::class);

            if (! $registry->has($providerId)) {
                $run->markFailed('Unsupported AI provider.', logContext: 'Launcher run failed during provider setup');

                return;
            }

            $resolver = app(LaunchAiKeyResolver::class);
            $apiKey = $resolver->resolve($providerId, $this->apiKey, $this->providerCredentialId);

            if ($apiKey === null || $apiKey === '') {
                $run->markFailed('No AI provider API key is available. Paste a key on launch, choose a saved key in API Keys, or configure OPENAI_API_KEY on the server.', logContext: 'Launcher run failed during provider setup');

                return;
            }

            $ai = $registry->get($providerId, $apiKey);

            $model = $this->model ?? $run->model;
            if ($model !== null && $model !== '' && $run->model !== $model) {
                $run->update(['model' => $model]);
            }

            if ($this->providerCredentialId !== null) {
                ProviderCredential::where('id', $this->providerCredentialId)->update(['last_used_at' => now()]);
            }
        } catch (Throwable $e) {
            $run->markFailed('Run failed unexpectedly.', $e, 'Launcher run failed during provider setup');

            return;
        }

        $executor->execute($run->fresh(), $ai);
    }
}
