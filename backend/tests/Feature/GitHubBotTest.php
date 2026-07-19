<?php

namespace Tests\Feature;

use App\Jobs\ProcessGitHubBotCommandJob;
use App\Services\GitHubBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GitHubBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['github-bot.webhook_secret' => 'test-secret']);
    }

    // ── Webhook signature verification ──────────────────────────────────

    public function test_rejects_unsigned_webhook(): void
    {
        $this->postJson('/api/github/webhooks', $this->commentPayload())
            ->assertStatus(401)
            ->assertJson(['message' => 'Invalid signature.']);
    }

    public function test_rejects_invalid_signature(): void
    {
        $this->postJson('/api/github/webhooks', $this->commentPayload(), [
            'X-Hub-Signature-256' => 'sha256=badhash',
            'X-GitHub-Event' => 'issue_comment',
        ])
            ->assertStatus(401)
            ->assertJson(['message' => 'Invalid signature.']);
    }

    public function test_accepts_valid_signature(): void
    {
        Queue::fake();
        $payload = $this->commentPayload();
        $payloadJson = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $payloadJson, 'test-secret');

        $this->postJson('/api/github/webhooks', $payload, [
            'X-Hub-Signature-256' => $signature,
            'X-GitHub-Event' => 'issue_comment',
        ])
            ->assertStatus(202)
            ->assertJson(['message' => 'Bot command queued.']);
    }

    // ── Event type filtering ────────────────────────────────────────────

    public function test_ignores_non_issue_comment_events(): void
    {
        $payload = json_encode(['action' => 'opened']);

        $this->postJson('/api/github/webhooks', json_decode($payload, true), [
            'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $payload, 'test-secret'),
            'X-GitHub-Event' => 'pull_request',
        ])
            ->assertStatus(200)
            ->assertJson(['message' => 'Event type ignored.']);
    }

    public function test_ignores_non_created_comment_actions(): void
    {
        $payload = $this->commentPayload(action: 'edited');

        $this->postJson('/api/github/webhooks', $payload, [
            'X-Hub-Signature-256' => 'sha256='.$this->sign($payload),
            'X-GitHub-Event' => 'issue_comment',
        ])
            ->assertStatus(200)
            ->assertJson(['message' => 'Comment action ignored.']);
    }

    // ── Private repo rejection ──────────────────────────────────────────

    public function test_rejects_private_repositories(): void
    {
        $payload = $this->commentPayload();
        $payload['repository']['private'] = true;

        $this->postJson('/api/github/webhooks', $payload, [
            'X-Hub-Signature-256' => 'sha256='.$this->sign($payload),
            'X-GitHub-Event' => 'issue_comment',
        ])
            ->assertStatus(200)
            ->assertJson(['message' => 'Private repositories are not supported.']);
    }

    // ── Command parsing ─────────────────────────────────────────────────

    public function test_ignores_comments_without_ai_flow_mention(): void
    {
        $payload = $this->commentPayload(body: 'Just a regular comment about the PR.');

        $this->postJson('/api/github/webhooks', $payload, [
            'X-Hub-Signature-256' => 'sha256='.$this->sign($payload),
            'X-GitHub-Event' => 'issue_comment',
        ])
            ->assertStatus(200)
            ->assertJson(['message' => 'No ai-flow command found.']);
    }

    public function test_ignores_bot_comments(): void
    {
        $payload = $this->commentPayload(userType: 'Bot');

        $this->postJson('/api/github/webhooks', $payload, [
            'X-Hub-Signature-256' => 'sha256='.$this->sign($payload),
            'X-GitHub-Event' => 'issue_comment',
        ])
            ->assertStatus(200)
            ->assertJson(['message' => 'Ignoring bot comment.']);
    }

    public function test_parses_review_command_on_pull_request(): void
    {
        Queue::fake();
        $payload = $this->commentPayload(
            body: '@ai-flow review this PR please',
            hasPr: true,
        );

        $this->postJson('/api/github/webhooks', $payload, [
            'X-Hub-Signature-256' => 'sha256='.$this->sign($payload),
            'X-GitHub-Event' => 'issue_comment',
        ])
            ->assertStatus(202);

        Queue::assertPushed(ProcessGitHubBotCommandJob::class, function (ProcessGitHubBotCommandJob $job): bool {
            return $job->launcherSlug === 'review-pr'
                && str_contains($job->sourceUrl, '/pull/');
        });
    }

    public function test_parses_plan_command_on_issue(): void
    {
        Queue::fake();
        $payload = $this->commentPayload(
            body: 'Lets get a plan for this @ai-flow plan',
            hasPr: false,
        );

        $this->postJson('/api/github/webhooks', $payload, [
            'X-Hub-Signature-256' => 'sha256='.$this->sign($payload),
            'X-GitHub-Event' => 'issue_comment',
        ])
            ->assertStatus(202);

        Queue::assertPushed(ProcessGitHubBotCommandJob::class, function (ProcessGitHubBotCommandJob $job): bool {
            return $job->launcherSlug === 'plan-issue'
                && ! str_contains($job->sourceUrl, '/pull/');
        });
    }

    public function test_parses_explain_command(): void
    {
        Queue::fake();
        $payload = $this->commentPayload(body: '@ai-flow explain');

        $this->postJson('/api/github/webhooks', $payload, [
            'X-Hub-Signature-256' => 'sha256='.$this->sign($payload),
            'X-GitHub-Event' => 'issue_comment',
        ])
            ->assertStatus(202);

        Queue::assertPushed(ProcessGitHubBotCommandJob::class, function (ProcessGitHubBotCommandJob $job): bool {
            return $job->launcherSlug === 'explain-repository';
        });
    }

    public function test_parses_doctor_command(): void
    {
        Queue::fake();
        $payload = $this->commentPayload(body: '@ai-flow doctor please');

        $this->postJson('/api/github/webhooks', $payload, [
            'X-Hub-Signature-256' => 'sha256='.$this->sign($payload),
            'X-GitHub-Event' => 'issue_comment',
        ])
            ->assertStatus(202);

        Queue::assertPushed(ProcessGitHubBotCommandJob::class, function (ProcessGitHubBotCommandJob $job): bool {
            return $job->launcherSlug === 'laravel-doctor';
        });
    }

    public function test_command_parsing_is_case_insensitive(): void
    {
        Queue::fake();
        $payload = $this->commentPayload(body: '@AI-FLOW REVIEW');

        $this->postJson('/api/github/webhooks', $payload, [
            'X-Hub-Signature-256' => 'sha256='.$this->sign($payload),
            'X-GitHub-Event' => 'issue_comment',
        ])
            ->assertStatus(202);

        Queue::assertPushed(ProcessGitHubBotCommandJob::class, function (ProcessGitHubBotCommandJob $job): bool {
            return $job->launcherSlug === 'review-pr';
        });
    }

    // ─── GitHubBotService unit tests ───────────────────────────────────

    public function test_parse_command_returns_null_for_no_command(): void
    {
        $bot = app(GitHubBotService::class);

        $this->assertNull($bot->parseCommand('Just a normal comment.'));
        $this->assertNull($bot->parseCommand('@mention but not ai-flow'));
        $this->assertNull($bot->parseCommand(''));
    }

    public function test_parse_command_detects_all_commands(): void
    {
        $bot = app(GitHubBotService::class);

        $this->assertSame(['command' => 'review', 'launcher' => 'review-pr'], $bot->parseCommand('@ai-flow review'));
        $this->assertSame(['command' => 'plan', 'launcher' => 'plan-issue'], $bot->parseCommand('@ai-flow plan'));
        $this->assertSame(['command' => 'explain', 'launcher' => 'explain-repository'], $bot->parseCommand('@ai-flow explain'));
        $this->assertSame(['command' => 'doctor', 'launcher' => 'laravel-doctor'], $bot->parseCommand('@ai-flow doctor'));
    }

    public function test_parse_command_works_with_additional_text(): void
    {
        $bot = app(GitHubBotService::class);

        $result = $bot->parseCommand('Can you @ai-flow review this PR? It needs a security check.');

        $this->assertNotNull($result);
        $this->assertSame('review-pr', $result['launcher']);
    }

    public function test_parse_command_only_matches_first_command(): void
    {
        $bot = app(GitHubBotService::class);

        // regex will match the first @ai-flow command — 'review'
        $result = $bot->parseCommand('@ai-flow review then @ai-flow plan');

        $this->assertNotNull($result);
        $this->assertSame('review-pr', $result['launcher']);
    }

    public function test_build_source_url_for_pull_request(): void
    {
        $bot = app(GitHubBotService::class);

        $url = $bot->buildSourceUrl('laravel', 'framework', 'pull_request', 42);

        $this->assertSame('https://github.com/laravel/framework/pull/42', $url);
    }

    public function test_build_source_url_for_issue(): void
    {
        $bot = app(GitHubBotService::class);

        $url = $bot->buildSourceUrl('laravel', 'framework', 'issue', 99);

        $this->assertSame('https://github.com/laravel/framework/issues/99', $url);
    }

    // ─── Progress and result comment formatting ─────────────────────────

    public function test_format_progress_comment_shows_correct_status(): void
    {
        $bot = app(GitHubBotService::class);

        $queued = $bot->formatProgressComment('ai-flow', 'review-pr', 'queued');
        $running = $bot->formatProgressComment('ai-flow', 'review-pr', 'running');
        $completed = $bot->formatProgressComment('ai-flow', 'review-pr', 'completed');
        $failed = $bot->formatProgressComment('ai-flow', 'review-pr', 'failed');

        $this->assertStringContainsString('<!-- ai-flow-comment -->', $queued);
        $this->assertStringContainsString('Queued', $queued);
        $this->assertStringContainsString('Analyzing with ai-flow', $running);
        $this->assertStringContainsString('Analysis complete', $completed);
        $this->assertStringContainsString('Analysis failed', $failed);
    }

    public function test_format_result_with_error_shows_error_block(): void
    {
        $bot = app(GitHubBotService::class);

        $result = $bot->formatResultComment('ai-flow', 'review-pr', [], 'Something went wrong.');

        $this->assertStringContainsString('❌', $result);
        $this->assertStringContainsString('failed', $result);
        $this->assertStringContainsString('Something went wrong.', $result);
    }

    public function test_format_result_with_summary_shows_findings(): void
    {
        $bot = app(GitHubBotService::class);

        $result = $bot->formatResultComment('ai-flow', 'review-pr', [
            'summary' => 'All good!',
            'risk' => 'low',
            'findings' => [['title' => 'Minor bug']],
            'verification_steps' => ['Step 1'],
        ], null);

        $this->assertStringContainsString('🤖', $result);
        $this->assertStringContainsString('All good!', $result);
        $this->assertStringContainsString('Risk level', $result);
        $this->assertStringContainsString('1 issue(s) identified.', $result);
    }

    // ─── ProcessGitHubBotCommandJob dispatching from webhook ────────────

    public function test_webhook_dispatches_job_with_correct_parameters(): void
    {
        Queue::fake();
        $payload = $this->commentPayload(
            body: '@ai-flow review',
            owner: 'test-owner',
            repo: 'test-repo',
            number: 123,
            hasPr: true,
        );

        $this->postJson('/api/github/webhooks', $payload, [
            'X-Hub-Signature-256' => 'sha256='.$this->sign($payload),
            'X-GitHub-Event' => 'issue_comment',
        ])
            ->assertStatus(202);

        Queue::assertPushed(ProcessGitHubBotCommandJob::class, function (ProcessGitHubBotCommandJob $job): bool {
            return $job->owner === 'test-owner'
                && $job->repo === 'test-repo'
                && $job->number === 123
                && $job->sourceUrl === 'https://github.com/test-owner/test-repo/pull/123'
                && $job->launcherSlug === 'review-pr';
        });
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function sign(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload), 'test-secret');
    }

    private function commentPayload(
        string $body = '@ai-flow review',
        string $action = 'created',
        string $owner = 'laravel',
        string $repo = 'framework',
        int $number = 1,
        bool $hasPr = true,
        string $userType = 'User',
    ): array {
        $issue = [
            'number' => $number,
            'title' => 'Test Issue',
            'body' => 'Issue body',
            'state' => 'open',
        ];

        if ($hasPr) {
            $issue['pull_request'] = ['url' => "https://api.github.com/repos/{$owner}/{$repo}/pulls/{$number}"];
        }

        return [
            'action' => $action,
            'issue' => $issue,
            'comment' => [
                'id' => 999,
                'body' => $body,
                'user' => [
                    'login' => 'test-user',
                    'type' => $userType,
                ],
            ],
            'repository' => [
                'name' => $repo,
                'full_name' => "{$owner}/{$repo}",
                'private' => false,
                'owner' => [
                    'login' => $owner,
                ],
            ],
        ];
    }
}
