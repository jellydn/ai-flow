<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Verifies GitHub webhook signatures and filters event types.
 *
 * Keeps the controller thin by handling signature verification,
 * event-type filtering, and private-repo rejection before the
 * request reaches the controller.
 */
class VerifyGitHubWebhook
{
    /**
     * Handle an incoming GitHub webhook request.
     */
    public function handle(Request $request, Closure $next)
    {
        // ── Signature verification ──────────────────────────────────────
        $secret = config('github-bot.webhook_secret');

        if (blank($secret)) {
            Log::warning('GitHub webhook received but GITHUB_WEBHOOK_SECRET is not configured.');

            return response()->json(['message' => 'Webhook secret not configured.'], 500);
        }

        $signature = $request->header('X-Hub-Signature-256', '');
        $payload = $request->getContent();
        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('GitHub webhook signature verification failed.');

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

        return $next($request);
    }
}
