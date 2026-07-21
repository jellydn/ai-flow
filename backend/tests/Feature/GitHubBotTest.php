<?php

namespace Tests\Feature;

use App\Jobs\ExecuteLauncherJob;
use App\Jobs\ProcessGitHubBotCommandJob;
use App\Models\Launcher;
use App\Models\Run;
use App\Services\GitHubBotService;
use App\Services\GitHubService;
use App\Services\LauncherResolutionService;
use App\Support\AiProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class GitHubBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['github-bot.webhook_secret' => 'test-secret']);
        Cache::flush();
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
        $this->fakeRepoConfig404();
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
        $this->fakeRepoConfig404();
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
        $this->fakeRepoConfig404();
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
        $this->fakeRepoConfig404();
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
        $this->fakeRepoConfig404();
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
        $this->fakeRepoConfig404();
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
        $this->fakeRepoConfig404();
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
                && $job->launcherSlug === 'review-pr'
                && $job->installationId === 42;
        });
    }

    // ─── GitHubBotService config / URL / YAML behavior ──────────────────

    public function test_is_launcher_enabled_caches_missing_config_so_subsequent_calls_skip_http(): void
    {
        // Use unique owner/repo to avoid interference from cache entries
        // left by other tests.
        Http::fake([
            'api.github.com/*' => Http::response('', 404),
        ]);

        $bot = app(GitHubBotService::class);
        $cacheKey = 'github-bot:repo-config:cachetest:cached-repo:default';

        $this->assertTrue($bot->isLauncherEnabled('cachetest', 'cached-repo', 'review-pr'));

        // The sentinel (empty array, not null) must be cached so
        // Cache::remember() doesn't re-call GitHub on the next lookup.
        $this->assertTrue(Cache::has($cacheKey), 'Empty config sentinel must be cached.');
    }

    public function test_is_launcher_enabled_does_not_fatal_on_malformed_scalar_then_list_yaml(): void
    {
        // "enabled: yes" is a scalar; the following "- review-pr" list item
        // used to fatal with a TypeError by appending to a string.
        Http::fake([
            'api.github.com/repos/owner/repo/contents/.github/ai-flow.yml' => Http::response([
                'content' => base64_encode("enabled: yes\n- review-pr"),
            ], 200),
        ]);

        $bot = app(GitHubBotService::class);

        // Scalar `enabled` => no enabled list => all launchers enabled.
        $this->assertTrue($bot->isLauncherEnabled('owner', 'repo', 'review-pr'));
    }

    public function test_update_comment_patches_the_owner_repo_scoped_url(): void
    {
        $requests = [];
        Http::fake(function (Request $request) use (&$requests) {
            $requests[] = $request->method().' '.$request->url();

            return Http::response(['id' => 1], 200);
        });

        $bot = app(GitHubBotService::class);
        $bot->updateComment('jellydn', 'ai-flow', 12345, 'updated body');

        $this->assertContains(
            'PATCH https://api.github.com/repos/jellydn/ai-flow/issues/comments/12345',
            $requests,
            'updateComment must PATCH /repos/{owner}/{repo}/issues/comments/{commentId}',
        );

        // Build the URL string that the old (buggy) code would have used.
        $buggy = 'PATCH https://api.github.com/repos/issues/comments/12345';
        $this->assertNotContains($buggy, $requests, 'The old owner/repo-less URL must not be used.');
    }

    // ─── GitHub App auth: base64url JWT + installation token ────────────

    public function test_app_jwt_segments_are_base64url_encoded(): void
    {
        $service = app(GitHubService::class);
        $jwt = $this->callPrivate($service, 'appJwt', [123, $this->privateKey()]);

        $segments = explode('.', $jwt);
        $this->assertCount(3, $segments);

        foreach ($segments as $segment) {
            $this->assertMatchesRegularExpression(
                '/^[A-Za-z0-9_-]+$/',
                $segment,
                "JWT segment must be base64url (no +, /, or =): {$segment}",
            );
        }
    }

    public function test_installation_token_uses_provided_installation_id_without_listing(): void
    {
        $privateKey = $this->privateKey();
        config(['github-bot.app_id' => '99999', 'github-bot.app_private_key' => $privateKey]);
        Cache::flush();

        $hits = [];
        Http::fake(function (Request $request) use (&$hits) {
            $hits[] = $request->method().' '.$request->url();

            if ($request->method() === 'POST' && str_ends_with($request->url(), '/app/installations/42/access_tokens')) {
                return Http::response(['token' => 'ghs_install_42'], 200);
            }

            return Http::response('', 404);
        });
        Http::preventStrayRequests();

        $service = app(GitHubService::class);
        $token = $this->callPrivate($service, 'appInstallationToken', [99999, $privateKey, 42]);

        $this->assertSame('ghs_install_42', $token);

        $listCall = array_filter($hits, fn (string $hit): bool => str_contains($hit, 'GET') && str_ends_with($hit, '/app/installations'));
        $this->assertEmpty($listCall, 'Must not list installations when installationId is provided');
    }

    // ─── ProcessGitHubBotCommandJob two-phase execution ──────────────────

    public function test_job_initialization_posts_progress_and_dispatches_delayed_continuation(): void
    {
        Queue::fake();

        $bot = Mockery::mock(GitHubBotService::class, [app(GitHubService::class)])->makePartial();
        $bot->expects('postComment')->with('jellydn', 'ai-flow', 1, Mockery::any(), null)->andReturn(999);
        // "running" progress update.
        $bot->expects('updateComment')->with('jellydn', 'ai-flow', 999, Mockery::any(), null)->once();

        $job = new ProcessGitHubBotCommandJob(
            owner: 'jellydn',
            repo: 'ai-flow',
            number: 1,
            sourceUrl: 'https://github.com/jellydn/ai-flow/pull/1',
            launcherSlug: 'review-pr',
            commentLabel: 'ai-flow',
        );
        $job->handle(
            $bot,
            app(LauncherResolutionService::class),
            app(AiProviderRegistry::class),
            app(GitHubService::class),
        );

        // The AI run job is dispatched...
        Queue::assertPushed(ExecuteLauncherJob::class);
        // ...and a delayed continuation carrying commentId + runId.
        Queue::assertPushed(ProcessGitHubBotCommandJob::class, function (ProcessGitHubBotCommandJob $c): bool {
            return $c->runId !== null && $c->commentId === 999;
        });
    }

    public function test_job_continuation_posts_result_when_run_is_terminal(): void
    {
        Queue::fake();

        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'review-pr')->value('id'),
            'source_url' => 'https://github.com/jellydn/ai-flow/pull/1',
            'input' => ['source_url' => 'https://github.com/jellydn/ai-flow/pull/1'],
            'status' => 'completed',
            'result' => ['summary' => 'Done!'],
            'progress' => [],
        ]);

        $bot = Mockery::mock(GitHubBotService::class, [app(GitHubService::class)])->makePartial();
        $bot->expects('updateComment')
            ->with('jellydn', 'ai-flow', 999, Mockery::on(fn (string $body): bool => str_contains($body, 'Done!')), null)
            ->once();

        $job = new ProcessGitHubBotCommandJob(
            owner: 'jellydn',
            repo: 'ai-flow',
            number: 1,
            sourceUrl: 'https://github.com/jellydn/ai-flow/pull/1',
            launcherSlug: 'review-pr',
            commentLabel: 'ai-flow',
            commentId: 999,
            runId: $run->id,
        );
        $job->handle($bot, app(LauncherResolutionService::class), app(AiProviderRegistry::class), app(GitHubService::class));

        Queue::assertNotPushed(ProcessGitHubBotCommandJob::class);
    }

    public function test_job_continuation_redispatches_when_run_still_pending(): void
    {
        Queue::fake();

        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'review-pr')->value('id'),
            'source_url' => 'https://github.com/jellydn/ai-flow/pull/1',
            'input' => ['source_url' => 'https://github.com/jellydn/ai-flow/pull/1'],
            'status' => 'running',
            'progress' => [],
        ]);

        $bot = Mockery::mock(GitHubBotService::class, [app(GitHubService::class)])->makePartial();
        $bot->shouldNotReceive('updateComment');

        $job = new ProcessGitHubBotCommandJob(
            owner: 'jellydn',
            repo: 'ai-flow',
            number: 1,
            sourceUrl: 'https://github.com/jellydn/ai-flow/pull/1',
            launcherSlug: 'review-pr',
            commentLabel: 'ai-flow',
            commentId: 999,
            runId: $run->id,
        );
        $job->handle($bot, app(LauncherResolutionService::class), app(AiProviderRegistry::class), app(GitHubService::class));

        Queue::assertPushed(ProcessGitHubBotCommandJob::class, function (ProcessGitHubBotCommandJob $c) use ($run): bool {
            return $c->runId === $run->id && $c->commentId === 999;
        });
    }

    public function test_job_continuation_posts_timeout_comment_after_deadline(): void
    {
        Queue::fake();
        config(['github-bot.poll_max_seconds' => 0]);

        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'review-pr')->value('id'),
            'source_url' => 'https://github.com/jellydn/ai-flow/pull/1',
            'input' => ['source_url' => 'https://github.com/jellydn/ai-flow/pull/1'],
            'status' => 'running',
            'progress' => [],
        ]);
        // Push created_at into the past via a raw update so deadlineExceeded
        // sees a non-zero elapsed time (Eloquent $fillable excludes timestamps).
        \DB::table('runs')
            ->where('id', $run->id)
            ->update(['created_at' => now()->subMinutes(10)]);

        $bot = Mockery::mock(GitHubBotService::class, [app(GitHubService::class)])->makePartial();
        $bot->expects('updateComment')
            ->with('jellydn', 'ai-flow', 999, Mockery::on(fn (string $body): bool => str_contains($body, 'did not complete within the configured time limit')), null)
            ->once();

        $job = new ProcessGitHubBotCommandJob(
            owner: 'jellydn',
            repo: 'ai-flow',
            number: 1,
            sourceUrl: 'https://github.com/jellydn/ai-flow/pull/1',
            launcherSlug: 'review-pr',
            commentLabel: 'ai-flow',
            commentId: 999,
            runId: $run->id,
        );

        // Sanity-check: the config and run state must trigger the deadline.
        $this->assertSame(0, (int) config('github-bot.poll_max_seconds'));
        $refreshed = Run::find($run->id);
        $this->assertNotNull($refreshed->created_at, 'created_at must survive the raw update');

        // Verify deadlineExceeded returns true before relying on it in the job loop.
        $elapsed = abs(Date::now()->diffInSeconds($refreshed->created_at));
        $this->assertGreaterThanOrEqual(0, $elapsed, "elapsed seconds: {$elapsed}, max: 0");
        $this->assertTrue(
            $this->callPrivate($job, 'deadlineExceeded', [$refreshed]),
            'deadlineExceeded must return true (elapsed='.$elapsed.', max=0)',
        );

        $job->handle($bot, app(LauncherResolutionService::class), app(AiProviderRegistry::class), app(GitHubService::class));

        Queue::assertNotPushed(ProcessGitHubBotCommandJob::class);
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function callPrivate(object $object, string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($object, $method);

        return $reflection->invokeArgs($reflection->isStatic() ? null : $object, $args);
    }

    private function privateKey(): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $out);

        return $out;
    }

    private function fakeRepoConfig404(): void
    {
        // Stub the .github/ai-flow.yml lookup so every dispatch-path test
        // stays offline. A missing config (404) is treated as "all launchers
        // enabled". Targeted tests override this with their own Http::fake().
        Http::fake([
            'api.github.com/*' => Http::response('', 404),
        ]);
    }

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
            'installation' => ['id' => 42],
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
