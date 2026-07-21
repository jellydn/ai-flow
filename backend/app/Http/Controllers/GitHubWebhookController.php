<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessGitHubBotCommandJob;
use App\Services\GitHubBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives GitHub webhook events and dispatches bot commands.
 *
 * Signature verification, event filtering, and private-repo rejection
 * are handled by the VerifyGitHubWebhook middleware — this controller
 * only parses commands and dispatches jobs.
 */
class GitHubWebhookController extends Controller
{
    public function __construct(private GitHubBotService $bot) {}

    public function __invoke(Request $request): JsonResponse
    {
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
        $repoData = $request->json('repository', []);
        $owner = $repoData['owner']['login'] ?? '';
        $repo = $repoData['name'] ?? '';
        $issue = $request->json('issue', []);
        $number = (int) ($issue['number'] ?? 0);

        // GitHub App installation ID — present on issue_comment webhooks as
        // installation.id. Threaded through to token generation so multi-
        // installation apps mint tokens for the correct installation.
        $installationId = $request->json('installation.id') !== null
            ? (int) $request->json('installation.id')
            : null;

        // ── Per-repo config check ──────────────────────────────────────
        if (! $this->bot->isLauncherEnabled($owner, $repo, $parsed['launcher'], $installationId)) {
            return response()->json([
                'message' => "Launcher '{$parsed['launcher']}' is disabled for this repository.",
            ], 200);
        }

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
            installationId: $installationId,
        );

        return response()->json(['message' => 'Bot command queued.'], 202);
    }
}
