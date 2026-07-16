<?php

namespace App\Services;

use App\Contracts\AIProviderInterface;
use App\Events\RunProgressed;
use App\Models\Run;
use RuntimeException;
use Throwable;

class RunExecutor
{
    public function __construct(
        private GitHubService $github,
        private ContextEncoder $encoder,
        private JsonSchemaValidator $validator,
    ) {}

    public function execute(Run $run, AIProviderInterface $ai): void
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
            $basePrompt = $run->prompt_snapshot ?? $run->launcher?->prompt_template ?? '';
            $prompt = $basePrompt."\nGitHub context:\n".$this->encoder->encode($context);
            $model = $run->model;
            $result = $ai->generate($prompt, $run->launcher->output_schema, $model);
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
            $run->markFailed($message, $e);
            \Sentry\captureException($e);
        }
    }

    private function progress(Run $run, string $message, bool $start = false): void
    {
        $run->update(['status' => 'running', 'progress' => [...($run->progress ?? []), $message]] + ($start ? ['started_at' => now()] : []));
        RunProgressed::dispatch($run->fresh());
    }
}
