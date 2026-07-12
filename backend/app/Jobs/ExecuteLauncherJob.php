<?php

namespace App\Jobs;

use App\Contracts\AIProviderInterface;
use App\Events\RunProgressed;
use App\Models\Run;
use App\Services\GitHubService;
use App\Services\JsonSchemaValidator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ExecuteLauncherJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public string $runId) {}

    public function handle(GitHubService $github, AIProviderInterface $ai, JsonSchemaValidator $validator): void
    {
        $run = Run::with('launcher')->findOrFail($this->runId);
        try {
            $this->progress($run, 'Fetching repository', true);
            $ref = $github->parse($run->source_url);
            if ($run->launcher->input_type !== $ref->type) {
                throw new RuntimeException("This launcher requires a {$run->launcher->input_type} URL.");
            }
            if ($ref->type === 'pull_request') {
                $this->progress($run, 'Reading changed files');
            }
            $context = $github->context($run->source_url);
            $run->update(['source_context' => $context]);
            $this->progress($run, 'Running AI analysis');
            $contextJson = json_encode($context, JSON_UNESCAPED_SLASHES);
            $maxContextBytes = 120_000;
            if (strlen($contextJson) > $maxContextBytes) {
                $contextJson = substr($contextJson, 0, $maxContextBytes).'…[truncated]';
            }
            $prompt = $run->launcher->prompt_template."\nGitHub context:\n".$contextJson;
            $result = $ai->generate($prompt, $run->launcher->output_schema);
            $validator->validate($result, $run->launcher->output_schema);
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
            $run->update(['status' => 'failed', 'error' => $message, 'completed_at' => now()]);
            Log::error('Launcher run failed', ['run_id' => $run->id, 'exception' => get_class($e)]);
            RunProgressed::dispatch($run->fresh());
        }
    }

    private function progress(Run $run, string $message, bool $start = false): void
    {
        $run->update(['status' => 'running', 'progress' => [...($run->progress ?? []), $message]] + ($start ? ['started_at' => now()] : []));
        RunProgressed::dispatch($run->fresh());
    }
}
