<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GitHub Bot Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the ai-flow GitHub bot that responds to @ai-flow commands
    | in issue and pull request comments.
    |
    */

    // Webhook secret used to verify X-Hub-Signature-256.
    // Set via GITHUB_WEBHOOK_SECRET. Required to receive webhooks.
    'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),

    // GitHub App credentials (preferred auth mode).
    // If both are set, the bot uses installation tokens scoped to the repo.
    // Falls back to GITHUB_TOKEN if App credentials are absent.
    'app_id' => env('GITHUB_APP_ID'),
    'app_private_key' => env('GITHUB_APP_PRIVATE_KEY'),

    // Label applied to bot comments so they can be identified for updates.
    'comment_label' => env('GITHUB_BOT_COMMENT_LABEL', 'ai-flow'),

    // Command mapping: comment command → launcher slug.
    'commands' => [
        'review' => 'review-pr',
        'plan' => 'plan-issue',
        'explain' => 'explain-repository',
        'doctor' => 'laravel-doctor',
    ],

    // Maximum length of result summary posted as a comment (characters).
    'result_max_length' => 2000,

    // ── Polling (ProcessGitHubBotCommandJob) ───────────────────────────
    //
    // The job polls Run.status until it reaches a terminal state.
    // job_timeout must exceed max_poll_seconds so there's a buffer
    // for the comment-posting steps before/after the poll loop.

    // Maximum seconds to poll for run completion.
    'poll_max_seconds' => env('GITHUB_BOT_POLL_MAX_SECONDS', 150),

    // Interval between polls in milliseconds.
    'poll_interval_ms' => env('GITHUB_BOT_POLL_INTERVAL_MS', 2000),

    // Total job timeout (must be > poll_max_seconds + overhead).
    'job_timeout' => env('GITHUB_BOT_JOB_TIMEOUT', 180),

    // Webhook rate limit (requests per minute).
    'webhook_rate_limit' => env('GITHUB_BOT_WEBHOOK_RATE_LIMIT', 60),

];
