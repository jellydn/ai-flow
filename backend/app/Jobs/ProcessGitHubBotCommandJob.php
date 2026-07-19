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
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Processes an @ai-flow command from a GitHub comment.
 *
 * 1. Posts a progress comment on the issue/PR.
 * 2. Creates a Run via the existing launcher resolution pipeline.
 * 3. Dispatches ExecuteLauncherJob for the actual AI work.
 * 4. Polls for completion and updates the progress comment with results.
 */
class ProcessGitHubBotCommandJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout;

    public function __construct(
        public string $owner,
        public string $repo,
        public int $number,
        public string $sourceUrl,
        public string $launcherSlug,
        public string $commentLabel,
    ) {
        $this->timeout = (int) config('github-bot.job_timeout', 180);
    }

    public function handle(
        GitHubBotService $bot,
        LauncherResolutionService $launcherResolver,
        AiProviderRegistry $providerRegistry,
        GitHubService $github,
    ): void {
        $label = $this->commentLabel;

        try {
            $commentId = $this->postProgressComment($bot, $label);
            $run = $this->createAndExecuteRun($bot, $label, $commentId, $launcherResolver, $providerRegistry, $github);
            $this->postResultComment($bot, $label, $commentId, $run);

        } catch (Throwable $e) {
            // Best-effort: try to post an error comment if we have a comment ID.
            if (isset($commentId)) {
                try {
                    $bot->updateComment(
                        $commentId,
                        $bot->formatResultComment(
                            $label,
                            $this->launcherSlug,
                            [],
                            'Internal error: '.$e->getMessage(),
                        ),
                    );
                } catch (Throwable) {
                    // Can't even post the error comment — nothing more to do.
                }
            }

            throw $e;
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
        );
    }

    /**
     * Resolve the launcher, update progress to "running", create the Run,
     * dispatch ExecuteLauncherJob, and poll for completion.
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
        $bot->updateComment($commentId, $bot->formatProgressComment($label, $this->launcherSlug, 'running'));

        // ── Create Run ────────────────────────────────────────────────
        $params = LaunchParameters::resolve(
            providerId: null,
            oneTimeApiKey: null,
            providerCredentialId: null,
            requestedModel: null,
            registry: $providerRegistry,
            allowCustom: false,
        );

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

        return $this->pollForCompletion($run->id);
    }

    /**
     * Post the final result comment.
     */
    private function postResultComment(GitHubBotService $bot, string $label, int $commentId, Run $run): void
    {
        $resultComment = $bot->formatResultComment(
            $label,
            $this->launcherSlug,
            array_merge((array) $run->result, ['run_id' => $run->id]),
            $run->error,
        );

        $bot->updateComment($commentId, $resultComment);
    }

    // ── Polling ──────────────────────────────────────────────────────────

    /**
     * Poll the run until it reaches a terminal state.
     *
     * Timeout from config (github-bot.poll_max_seconds) with interval
     * from config (github-bot.poll_interval_ms). Total job timeout
     * (github-bot.job_timeout) must exceed poll_max_seconds so there's
     * budget for the comment-posting steps before/after the loop.
     */
    private function pollForCompletion(string $runId): Run
    {
        $maxPollSeconds = (int) config('github-bot.poll_max_seconds', 150);
        $pollIntervalMs = (int) config('github-bot.poll_interval_ms', 2000);
        $elapsed = 0;

        while ($elapsed < $maxPollSeconds) {
            /** @var Run $run */
            $run = Run::find($runId);

            if ($run === null) {
                throw new RuntimeException("Run {$runId} not found during polling.");
            }

            if (in_array($run->status, Run::TERMINAL_STATUSES, true)) {
                return $run;
            }

            usleep($pollIntervalMs * 1000);
            $elapsed += ($pollIntervalMs / 1000);
        }

        throw new RuntimeException("Run {$runId} did not complete within {$maxPollSeconds}s.");
    }
}
