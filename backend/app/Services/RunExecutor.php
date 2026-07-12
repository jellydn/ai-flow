<?php

namespace App\Services;

use App\Contracts\AIProviderInterface;
use App\Contracts\RunExecutorInterface;
use App\Events\RunProgressed;
use App\Models\Run;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RunExecutor implements RunExecutorInterface
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
            $prompt = $run->launcher->prompt_template."\nGitHub context:\n".$this->encoder->encode($context);
            $result = $ai->generate($prompt, $run->launcher->output_schema);
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
            Log::error('Launcher run failed', ['run_id' => $run->id, 'exception' => $e]);
            RunProgressed::dispatch($run->fresh());
        }
    }

    private function progress(Run $run, string $message, bool $start = false): void
    {
        $run->update(['status' => 'running', 'progress' => [...($run->progress ?? []), $message]] + ($start ? ['started_at' => now()] : []));
        RunProgressed::dispatch($run->fresh());
    }
}
