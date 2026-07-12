<?php

namespace App\Services;

use App\Contracts\AIProviderFactoryInterface;
use App\Contracts\RunExecutorInterface;
use App\Events\RunProgressed;
use App\Models\Run;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RunExecutor implements RunExecutorInterface
{
    private const MAX_CONTEXT_BYTES = 120_000;

    public function __construct(
        private GitHubService $github,
        private AIProviderFactoryInterface $providers,
        private JsonSchemaValidator $validator,
    ) {}

    public function execute(Run $run, string $provider = 'openai', ?string $apiKey = null): void
    {
        $run->loadMissing('launcher');

        try {
            $this->progress($run, 'Fetching repository', true);
            $ref = $this->github->parse($run->source_url);
            if ($run->launcher->input_type !== $ref->type) {
                throw new RuntimeException("This launcher requires a {$run->launcher->input_type} URL.");
            }
            if ($ref->type === 'pull_request') {
                $this->progress($run, 'Reading changed files');
            }
            $context = $this->github->context($run->source_url);
            $run->update(['source_context' => $context]);
            $this->progress($run, 'Running AI analysis');
            $prompt = $run->launcher->prompt_template."\nGitHub context:\n".$this->encodeContext($context);
            $result = $this->providers->forExecution($provider, $apiKey)->generate($prompt, $run->launcher->output_schema);
            $this->validator->validate($result, $run->launcher->output_schema);
            $this->progress($run, 'Preparing report');
            $run->update([
                'status' => 'completed',
                'result' => $result,
                'source_context' => null,
                'completed_at' => now(),
            ]);
            RunProgressed::dispatch($run->fresh());
        } catch (Throwable $e) {
            $message = $e instanceof RuntimeException ? $e->getMessage() : 'Run failed unexpectedly.';
            $run->update(['status' => 'failed', 'error' => $message, 'source_context' => null, 'completed_at' => now()]);
            Log::error('Launcher run failed', ['run_id' => $run->id, 'exception' => get_class($e)]);
            RunProgressed::dispatch($run->fresh());
        }
    }

    private function encodeContext(array $context): string
    {
        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($encoded) <= self::MAX_CONTEXT_BYTES) {
            return $encoded;
        }

        $bounded = $context;
        $bounded['truncated'] = true;
        if (isset($bounded['repository'])) {
            $bounded['repository']['readme'] = mb_substr($bounded['repository']['readme'] ?? '', 0, 10_000);
            $bounded['repository']['file_tree'] = array_slice($bounded['repository']['file_tree'] ?? [], 0, 250);
        }
        $bounded['changed_files'] = array_map(function (array $file): array {
            $file['diff'] = mb_substr($file['diff'] ?? '', 0, 1_000);

            return $file;
        }, array_slice($bounded['changed_files'] ?? [], 0, 30));
        $bounded['comments'] = array_map(function (array $comment): array {
            if (isset($comment['body'])) {
                $comment['body'] = mb_substr($comment['body'], 0, 1_000);
            }

            return $comment;
        }, array_slice($bounded['comments'] ?? [], 0, 10));

        $encoded = json_encode($bounded, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($encoded) <= self::MAX_CONTEXT_BYTES) {
            return $encoded;
        }

        return json_encode([
            'reference' => $bounded['reference'] ?? null,
            'repository' => array_intersect_key($bounded['repository'] ?? [], array_flip(['name', 'full_name', 'description', 'default_branch', 'languages'])),
            'issue' => $bounded['issue'] ?? null,
            'pull_request' => $bounded['pull_request'] ?? null,
            'truncated' => true,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function progress(Run $run, string $message, bool $start = false): void
    {
        $run->update(['status' => 'running', 'progress' => [...($run->progress ?? []), $message]] + ($start ? ['started_at' => now()] : []));
        RunProgressed::dispatch($run->fresh());
    }
}
