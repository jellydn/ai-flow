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
                'readme' => $raw['readmeContent'] !== null ? mb_substr($raw['readmeContent'], 0, 20000) : null,
                'file_tree' => array_slice(array_column($raw['tree'], 'path'), 0, 500),
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
                fn (array $f) => ['name' => $f['filename'], 'status' => $f['status'], 'diff' => mb_substr($f['patch'] ?? '', 0, 4000)],
                array_slice($raw['prFiles'], 0, 50),
            );
            $context['comments'] = array_map(
                fn (array $c) => ['author' => $c['user']['login'] ?? null, 'body' => mb_substr($c['body'] ?? '', 0, 3000)],
                array_slice($raw['prComments'], 0, 30),
            );
        } elseif ($ref->type === 'issue' && $raw['issue'] !== null) {
            $context['issue'] = array_intersect_key($raw['issue'], array_flip(['number', 'title', 'body', 'state', 'labels', 'user']));
            $context['comments'] = array_map(
                fn (array $comment) => [
                    'user' => $comment['user']['login'] ?? null,
                    'body' => mb_substr($comment['body'] ?? '', 0, 3000),
                ],
                array_slice($raw['issueComments'], 0, 30),
            );
        }

        return $context;
    }
}
