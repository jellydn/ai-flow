<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessGitHubBotCommandJob;
use App\Services\GitHubBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives GitHub webhook events and dispatches bot commands.
 *
 * Only processes issue_comment.created events that contain @ai-flow commands.
 * Returns 202 immediately — the actual work happens in a queue job.
 */
class GitHubWebhookController extends Controller
{
    public function __construct(private GitHubBotService $bot) {}

    public function __invoke(Request $request): JsonResponse
    {
        // ── Signature verification ──────────────────────────────────────
        $secret = config('github-bot.webhook_secret');

        if ($secret === null || $secret === '' || $secret === '0') {
            Log::warning('GitHub webhook received but GITHUB_WEBHOOK_SECRET is not configured.');

            return response()->json(['message' => 'Webhook secret not configured.'], 500);
        }

        $signature = $request->header('X-Hub-Signature-256', '');
        $payload = $request->getContent();
        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('GitHub webhook signature verification failed.', [
                'expected' => $expected,
                'received' => $signature,
            ]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        // ── Event type filtering ────────────────────────────────────────
        $event = $request->header('X-GitHub-Event', '');

        if ($event !== 'issue_comment') {
            return response()->json(['message' => 'Event type ignored.'], 200);
        }

        $action = $request->json('action');

        if ($action !== 'created') {
            return response()->json(['message' => 'Comment action ignored.'], 200);
        }

        // ── Public repo check ───────────────────────────────────────────
        $repoData = $request->json('repository', []);

        if (($repoData['private'] ?? false) === true) {
            Log::info('Ignoring webhook from private repository.', [
                'repo' => $repoData['full_name'] ?? 'unknown',
            ]);

            return response()->json(['message' => 'Private repositories are not supported.'], 200);
        }

        // ── Command parsing ─────────────────────────────────────────────
        $commentBody = $request->json('comment.body', '');
        $parsed = $this->bot->parseCommand($commentBody);

        if ($parsed === null) {
            return response()->json(['message' => 'No ai-flow command found.'], 200);
        }

        // Skip bot's own comments to prevent infinite loops.
        $commenterType = $request->json('comment.user.type', '');
        if ($commenterType === 'Bot') {
            return response()->json(['message' => 'Ignoring bot comment.'], 200);
        }

        // ── Extract PR/issue context ────────────────────────────────────
        $owner = $repoData['owner']['login'] ?? '';
        $repo = $repoData['name'] ?? '';
        $issue = $request->json('issue', []);
        $number = (int) ($issue['number'] ?? 0);

        // Determine if this is a PR or an issue. GitHub webhooks expose
        // issue.pull_request when the comment is on a PR.
        $type = isset($issue['pull_request']) ? 'pull_request' : 'issue';
        $sourceUrl = $this->bot->buildSourceUrl($owner, $repo, $type, $number);

        // ── Dispatch async job ──────────────────────────────────────────
        ProcessGitHubBotCommandJob::dispatch(
            owner: $owner,
            repo: $repo,
            number: $number,
            sourceUrl: $sourceUrl,
            launcherSlug: $parsed['launcher'],
            commentLabel: config('github-bot.comment_label', 'ai-flow'),
        );

        return response()->json(['message' => 'Bot command queued.'], 202);
    }
}
