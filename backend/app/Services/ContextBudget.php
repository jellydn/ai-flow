<?php

namespace App\Services;

/**
 * Shared context budget constants.
 *
 * The single source of truth for GitHub context size limits.
 * Both GitHubService::assemble() (initial fetch-time caps) and
 * ContextEncoder (byte-budget enforcement) reference these
 * so the two truncation passes stay aligned.
 */
final class ContextBudget
{
    /** Maximum encoded context size in bytes. */
    public const MAX_CONTEXT_BYTES = 120_000;

    // ── Fetch-time caps (GitHubService::assemble) ────────────────────

    /** Max readme excerpt length at fetch time (chars). */
    public const FETCH_README_LIMIT = 50_000;

    /** Max file tree entries at fetch time. */
    public const FETCH_FILE_TREE_LIMIT = 1000;

    /** Max changed files at fetch time. */
    public const FETCH_CHANGED_FILES_LIMIT = 100;

    /** Max diff excerpt length at fetch time (chars). */
    public const FETCH_DIFF_LIMIT = 8000;

    /** Max PR comments at fetch time. */
    public const FETCH_PR_COMMENTS_LIMIT = 50;

    /** Max PR comment body length at fetch time (chars). */
    public const FETCH_PR_COMMENT_BODY_LIMIT = 5000;

    /** Max issue comments at fetch time. */
    public const FETCH_ISSUE_COMMENTS_LIMIT = 30;

    /** Max issue comment body length at fetch time (chars). */
    public const FETCH_ISSUE_COMMENT_BODY_LIMIT = 3000;

    // ── Budget-tier caps (ContextEncoder second-pass truncation) ──────

    /** Max readme excerpt after budget truncation (chars). */
    public const BUDGET_README_LIMIT = 10_000;

    /** Max file tree entries after budget truncation. */
    public const BUDGET_FILE_TREE_LIMIT = 250;

    /** Max changed files after budget truncation. */
    public const BUDGET_CHANGED_FILES_LIMIT = 30;

    /** Max diff excerpt after budget truncation (chars). */
    public const BUDGET_DIFF_LIMIT = 1000;

    /** Max comments after budget truncation. */
    public const BUDGET_COMMENTS_LIMIT = 10;

    /** Max comment body after budget truncation (chars). */
    public const BUDGET_COMMENT_BODY_LIMIT = 1000;
}
