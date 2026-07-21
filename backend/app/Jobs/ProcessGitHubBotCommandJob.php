<?php

namespace App\Jobs;

use App\Models\Run;
use App\Services\GitHubBotService;
use App\Services\GitHubService;
use App\Services\LauncherResolutionService;
use App\Services\LaunchParameters;
use App\Support\AiProviderRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Processes an @ai-flow command from a GitHub comment.
 *
 * Runs in two phases to avoid blocking a queue worker while the AI run
 * executes (the previous implementation polled with a `while`/`usleep` loop,
 * which starved workers):
 *
 *  1. Initialization — post a "queued" comment, resolve the launcher, create
 *     the Run, dispatch ExecuteLauncherJob, then re-dispatch THIS job as a
 *     delayed continuation carrying the commentId/runId/installationId.
 *
 *  2. Continuation — check the Run status. If terminal, post the result
 *     comment. Otherwise re-dispatch another continuation until the overall
 *     deadline (github-bot.poll_max_seconds) elapses, at which point a generic
 *     timeout comment is posted.
 *
 * Each phase is short (per-execution timeout well below the 120s worker
 * limit), so the worker is free between polls. Exceptions are re-thrown so the
 * queue logs them internally; GitHub comments only ever show generic messages
 * to avoid leaking internals.
 */
class ProcessGitHubBotCommandJob implements ShouldQueue
{
    use Queueable;

    /**
     * Each phase is a single attempt — continuations re-dispatch a fresh job.
     */
    public int $tries = 1;

    public int $timeout;

    public function __construct(
        public string $owner,
        public string $repo,
        public int $number,
        public string $sourceUrl,
        public string $launcherSlug,
        public string $commentLabel,
        public ?int $installationId = null,
        public ?int $commentId = null,
        public ?string $runId = null,
    ) {
        // Per-execution timeout — safely below the production worker's 120s
        // limit so the process is never SIGKILLed mid-phase.
        $this->timeout = (int) config('github-bot.job_timeout', 60);
    }

    public function handle(
        GitHubBotService $bot,
        LauncherResolutionService $launcherResolver,
        AiProviderRegistry $providerRegistry,
        GitHubService $github,
    ): void {
        if ($this->runId === null) {
            $this->initialize($bot, $launcherResolver, $providerRegistry, $github);
        } else {
            $this->continue($bot);
        }
    }

    // ── Phase 1: initialization ──────────────────────────────────────────

    /**
     * Post the progress comment, create the Run, dispatch ExecuteLauncherJob,
     * then re-dispatch a delayed continuation to poll for completion.
     */
    private function initialize(
        GitHubBotService $bot,
        LauncherResolutionService $launcherResolver,
        AiProviderRegistry $providerRegistry,
        GitHubService $github,
    ): void {
        $label = $this->commentLabel;
        $commentId = null;

        try {
            $commentId = $this->postProgressComment($bot, $label);
            $run = $this->createAndExecuteRun($bot, $label, $commentId, $launcherResolver, $providerRegistry, $github);

            // Re-dispatch a delayed continuation to poll for completion,
            // freeing the worker while ExecuteLauncherJob runs.
            $this->dispatchContinuation($commentId, $run->id);
        } catch (Throwable $e) {
            $this->handleFailure($bot, $e, $commentId);
        }
    }

    // ── Phase 2: continuation (polling) ───────────────────────────────────

    /**
     * Check Run status: terminal → post result, deadline → post timeout,
     * otherwise re-dispatch another continuation.
     */
    private function continue(GitHubBotService $bot): void
    {
        $label = $this->commentLabel;

        // commentId is threaded through by dispatchContinuation on every
        // leg — a null here is a programming error (missing wire-up).
        // Treat it as a no-op so we don't PATCH /issues/comments/0.
        if ($this->commentId === null) {
            Log::warning('ProcessGitHubBotCommandJob::continue called without commentId.', [
                'run_id' => $this->runId,
                'owner' => $this->owner,
                'repo' => $this->repo,
                'number' => $this->number,
            ]);

            return;
        }

        try {
            /** @var Run|null $run */
            $run = Run::find($this->runId);

            if ($run === null) {
                throw new RuntimeException("Run {$this->runId} not found during polling.");
            }

            if (in_array($run->status, Run::TERMINAL_STATUSES, true)) {
                $this->postResultComment($bot, $label, $this->commentId, $run);

                return;
            }

            if ($this->deadlineExceeded($run)) {
                $bot->updateComment(
                    $this->owner,
                    $this->repo,
                    $this->commentId,
                    $bot->formatResultComment($label, $this->launcherSlug, [], $this->timeoutErrorMessage()),
                    $this->installationId,
                );

                return;
            }

            $this->dispatchContinuation($this->commentId, $run->id);
        } catch (Throwable $e) {
            $this->handleFailure($bot, $e, $this->commentId);
        }
    }

    // ── Private steps ────────────────────────────────────────────────────

    /**
     * Post the initial "queued" progress comment.
     */
    private function postProgressComment(GitHubBotService $bot, string $label): int
    {
        return $bot->postComment(
            $this->owner,
            $this->repo,
            $this->number,
            $bot->formatProgressComment($label, $this->launcherSlug, 'queued'),
            $this->installationId,
        );
    }

    /**
     * Resolve the launcher, update progress to "running", create the Run, and
     * dispatch ExecuteLauncherJob. Returns the created Run (does NOT poll).
     */
    private function createAndExecuteRun(
        GitHubBotService $bot,
        string $label,
        int $commentId,
        LauncherResolutionService $launcherResolver,
        AiProviderRegistry $providerRegistry,
        GitHubService $github,
    ): Run {
        // ── Resolve launcher ───────────────────────────────────────────
        $resolved = $launcherResolver->resolve($this->launcherSlug, null);

        if ($resolved->launcherId === null) {
            throw new RuntimeException('No active launchers are available.');
        }

        // ── Update progress to running ────────────────────────────────
        $bot->updateComment(
            $this->owner,
            $this->repo,
            $commentId,
            $bot->formatProgressComment($label, $this->launcherSlug, 'running'),
            $this->installationId,
        );

        // ── Create Run ────────────────────────────────────────────────
        $params = LaunchParameters::resolve(
            providerId: null,
            oneTimeApiKey: null,
            providerCredentialId: null,
            requestedModel: null,
            registry: $providerRegistry,
            allowCustom: false,
        );

        // Fail fast when no usable key is available — the bot path
        // bypasses StoreRunRequest validation so the guard that applies
        // to the HTTP API is not enforced here. Without this check the
        // job dispatches ExecuteLauncherJob, which fails, the Run ends
        // failed, and an error comment is posted — but the webhook still
        // returns 202 and queues a job guaranteed to fail.
        if (! $params->hasUsableKey()) {
            throw new RuntimeException(
                'No AI provider API key is available. Configure OPENAI_API_KEY or OPENROUTER_API_KEY on the server.'
            );
        }

        // Extract repo metadata from the source URL (same as RunController::store).
        $repoSlug = null;
        $repoType = null;
        try {
            $ref = $github->parse($this->sourceUrl);
            $repoSlug = "{$ref->owner}/{$ref->repo}";
            $repoType = $ref->type;
        } catch (InvalidArgumentException) {
            // Invalid URL — repo metadata stays null.
        }

        $run = Run::create([
            'launcher_id' => $resolved->launcherId,
            'user_launcher_id' => $resolved->userLauncherId,
            'user_id' => null,
            'provider' => $params->effectiveProvider,
            'model' => $params->resolvedModel,
            'source_url' => $this->sourceUrl,
            'repo_slug' => $repoSlug,
            'repo_type' => $repoType,
            'input' => ['source_url' => $this->sourceUrl],
            'prompt_snapshot' => $resolved->promptSnapshot,
            'is_public' => true,
            'status' => 'queued',
            'progress' => [],
        ]);

        ExecuteLauncherJob::dispatch(
            $run->id,
            $params->rawProviderId,
            $params->oneTimeApiKey,
            $params->providerCredentialId,
            $params->resolvedModel,
        );

        return $run;
    }

    /**
     * Post the final result comment.
     *
     * Public runs never expose raw error messages in comments — the same
     * guarantee handleFailure provides for exceptions thrown inside this job.
     * MarkFailed() stores arbitrary failure detail (exception strings,
     * API/infra messages) that must not leak into public GitHub repos.
     */
    private function postResultComment(GitHubBotService $bot, string $label, int $commentId, Run $run): void
    {
        $error = $run->error;

        if ($run->is_public && filled($error)) {
            $error = 'Internal error: The analysis did not complete successfully.';
        }

        $resultComment = $bot->formatResultComment(
            $label,
            $this->launcherSlug,
            array_merge((array) $run->result, ['run_id' => $run->id]),
            $error,
        );

        $bot->updateComment($this->owner, $this->repo, $commentId, $resultComment, $this->installationId);
    }

    // ── Continuation / polling helpers ───────────────────────────────────

    /**
     * Re-dispatch a delayed continuation job carrying the commentId/runId.
     */
    private function dispatchContinuation(int $commentId, string $runId): void
    {
        $delay = (int) config('github-bot.poll_interval_seconds', 5);

        self::dispatch(
            owner: $this->owner,
            repo: $this->repo,
            number: $this->number,
            sourceUrl: $this->sourceUrl,
            launcherSlug: $this->launcherSlug,
            commentLabel: $this->commentLabel,
            installationId: $this->installationId,
            commentId: $commentId,
            runId: $runId,
        )->delay(Date::now()->addSeconds($delay));
    }

    /**
     * Whether the run has exceeded the overall polling deadline.
     */
    private function deadlineExceeded(Run $run): bool
    {
        $maxSeconds = (int) config('github-bot.poll_max_seconds', 150);

        return $run->created_at !== null && abs(Date::now()->diffInSeconds($run->created_at)) >= $maxSeconds;
    }

    private function timeoutErrorMessage(): string
    {
        return 'The analysis did not complete within the configured time limit.';
    }

    /**
     * Log the exception internally and post a generic error comment so no
     * internals (DB queries, API keys, infrastructure) leak into public GitHub
     * comments. The exception is re-thrown so the queue records the failure.
     */
    private function handleFailure(GitHubBotService $bot, Throwable $e, ?int $commentId): void
    {
        if ($commentId !== null) {
            try {
                $bot->updateComment(
                    $this->owner,
                    $this->repo,
                    $commentId,
                    $bot->formatResultComment(
                        $this->commentLabel,
                        $this->launcherSlug,
                        [],
                        'Internal error: An unexpected exception occurred while processing the command.',
                    ),
                    $this->installationId,
                );
            } catch (Throwable) {
                // Can't even post the error comment — nothing more to do.
            }
        }

        throw $e;
    }
}
