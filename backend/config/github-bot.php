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

];
