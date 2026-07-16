<?php

namespace App\Services;

use App\Data\GitHubReference;

class GitHubContextAssembler
{
    /**
     * Assemble raw GitHub API data into a structured context array.
     *
     * @param  array  $raw  Raw data from GitHubContextFetcher::fetch().
     */
    public function assemble(GitHubReference $ref, array $raw): array
    {
        $context = [
            'reference' => $ref->toArray(),
            'repository' => [
                'name' => $raw['repo']['name'] ?? $ref->repo,
                'full_name' => $raw['repo']['full_name'] ?? "{$ref->owner}/{$ref->repo}",
                'description' => $raw['repo']['description'] ?? null,
                'default_branch' => $raw['repo']['default_branch'] ?? null,
                'languages' => $raw['languages'],
                'readme' => $raw['readmeContent'] !== null ? mb_substr($raw['readmeContent'], 0, ContextBudget::FETCH_README_LIMIT) : null,
                'file_tree' => array_slice(array_column($raw['tree'], 'path'), 0, ContextBudget::FETCH_FILE_TREE_LIMIT),
            ],
        ];

        if ($ref->type === 'pull_request' && $raw['pr'] !== null) {
            $context['pull_request'] = [
                'number' => $raw['pr']['number'],
                'title' => $raw['pr']['title'],
                'description' => $raw['pr']['body'] ?? '',
                'state' => $raw['pr']['state'],
                'author' => $raw['pr']['user']['login'] ?? null,
                'changed_files' => $raw['pr']['changed_files'] ?? count($raw['prFiles']),
            ];
            $context['changed_files'] = array_map(
                fn (array $f) => ['name' => $f['filename'], 'status' => $f['status'], 'diff' => mb_substr($f['patch'] ?? '', 0, ContextBudget::FETCH_DIFF_LIMIT)],
                array_slice($raw['prFiles'], 0, ContextBudget::FETCH_CHANGED_FILES_LIMIT),
            );
            $context['comments'] = array_map(
                fn (array $c) => ['author' => $c['user']['login'] ?? null, 'body' => mb_substr($c['body'] ?? '', 0, ContextBudget::FETCH_PR_COMMENT_BODY_LIMIT)],
                array_slice($raw['prComments'], 0, ContextBudget::FETCH_PR_COMMENTS_LIMIT),
            );
        } elseif ($ref->type === 'issue' && $raw['issue'] !== null) {
            $context['issue'] = array_intersect_key($raw['issue'], array_flip(['number', 'title', 'body', 'state', 'labels', 'user']));
            $context['comments'] = array_map(
                fn (array $comment) => [
                    'user' => $comment['user']['login'] ?? null,
                    'body' => mb_substr($comment['body'] ?? '', 0, ContextBudget::FETCH_ISSUE_COMMENT_BODY_LIMIT),
                ],
                array_slice($raw['issueComments'], 0, ContextBudget::FETCH_ISSUE_COMMENTS_LIMIT),
            );
        }

        return $context;
    }
}
