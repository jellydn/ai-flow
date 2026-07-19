<?php

namespace App\Services;

use App\Contracts\AIProviderInterface;
use App\Events\RunProgressed;
use App\Exceptions\UserFacingRunException;
use App\Models\Run;
use Illuminate\Http\Client\ConnectionException;
use InvalidArgumentException;
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
        $run->loadMissing(['launcher', 'userLauncher']);
        $launcher = $run->launcherSource();

        if ($launcher === null) {
            throw new RuntimeException('Run has no associated launcher.');
        }

        try {
            $this->progress($run, 'Fetching repository', true);
            try {
                $ref = $this->github->parse($run->source_url);
            } catch (InvalidArgumentException $e) {
                // Malformed / unsupported GitHub URLs are user input errors, not bugs.
                throw new UserFacingRunException($e->getMessage(), (int) $e->getCode(), $e);
            }
            if ($launcher->getInputType() !== $ref->type) {
                throw new UserFacingRunException("This launcher requires a {$launcher->getInputType()} URL.");
            }
            if ($ref->type === 'pull_request') {
                $this->progress($run, 'Reading changed files');
            }
            $context = $this->github->context($run->source_url);
            $run->update(['source_context' => $context]);
            $this->progress($run, 'Running AI analysis');
            $basePrompt = $run->prompt_snapshot ?? $launcher->getPromptTemplate() ?? '';
            $prompt = $basePrompt."\nGitHub context:\n".$this->encoder->encode($context);
            $model = $run->model;
            $outputSchema = $launcher->getOutputSchema();
            $result = $ai->generate($prompt, $outputSchema, $model);
            $this->validator->validate($result, $outputSchema);
            $this->progress($run, 'Preparing report');
            $run->update([
                'status' => 'completed',
                'result' => $result,
                'source_context' => null,
                'completed_at' => now(),
            ]);
            RunProgressed::dispatch($run->fresh());
        } catch (UserFacingRunException $e) {
            // Expected user/input failures (missing repo, wrong launcher URL, malformed URL, etc.)
            // Log at 'warning' so Sentry ignores these — they are not code bugs.
            $run->markFailed($e->getMessage(), $e, logLevel: 'warning');
        } catch (ConnectionException $e) {
            // Network-level failures reaching GitHub — transient, not code bugs.
            $run->markFailed('Unable to reach GitHub. Check your network connection and try again.', $e, logLevel: 'warning');
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            // Don't report expected AI-provider operational errors to Sentry.
            $isOperational = str_contains($message, 'API key is not configured')
                || str_contains($message, 'Invalid API key')
                || str_contains($message, 'Unable to reach the AI provider');

            $run->markFailed($message, $e, logLevel: $isOperational ? 'warning' : 'error');

            if (! $isOperational) {
                \Sentry\captureException($e);
            }
        } catch (Throwable $e) {
            // Unexpected errors — include the exception class for debugging
            $errorClass = class_basename($e);
            $run->markFailed("Run failed unexpectedly ({$errorClass}).", $e);
            \Sentry\captureException($e);
        }
    }

    private function progress(Run $run, string $message, bool $start = false): void
    {
        $run->update(['status' => 'running', 'progress' => [...($run->progress ?? []), $message]] + ($start ? ['started_at' => now()] : []));
        RunProgressed::dispatch($run->fresh());
    }
}
