<?php

namespace App\Services;

use App\Data\GitHubReference;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class GitHubService
{
    /** Parse a GitHub URL into a typed reference. */
    public function parse(string $url): GitHubReference
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $parts = explode('/', $path);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! in_array($host, ['github.com', 'www.github.com'], true) || count($parts) < 2 || count($parts) > 4 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidArgumentException('A public HTTPS github.com repository URL is required.');
        }
        $type = 'repository';
        $number = null;
        if (($parts[2] ?? null) === 'pull' && ctype_digit($parts[3] ?? '')) {
            $type = 'pull_request';
            $number = (int) $parts[3];
        } elseif (($parts[2] ?? null) === 'issues' && ctype_digit($parts[3] ?? '')) {
            $type = 'issue';
            $number = (int) $parts[3];
        } elseif (isset($parts[2])) {
            throw new InvalidArgumentException('The GitHub URL path is malformed or unsupported.');
        }

        return new GitHubReference(
            owner: $parts[0],
            repo: preg_replace('/\.git$/', '', $parts[1]),
            type: $type,
            number: $number,
        );
    }

    /** Fetch + assemble GitHub context for a URL, cached 10 min. */
    public function context(string $url): array
    {
        $ref = $this->parse($url);
        $key = 'github:'.sha1($url);

        return Cache::remember($key, now()->addMinutes(10), function () use ($ref) {
            $raw = $this->fetch($ref);

            return $this->assemble($ref, $raw);
        });
    }

    /**
     * Fetch raw GitHub API data for a parsed reference.
     *
     * @return array{repo: array, languages: array, readmeContent: string|null, tree: array, pr: array|null, prFiles: array, prComments: array, issue: array|null, issueComments: array}
     */
    public function fetch(GitHubReference $ref): array
    {
        try {
            return $this->fetchRaw($ref);
        } catch (RequestException $e) {
            throw $this->mapRequestException($e, $ref);
        }
    }

    /**
     * Assemble raw GitHub API data into structured context.
     *
     * @param  array  $raw  Raw data from fetch().
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

    // ── Private helpers ────────────────────────────────────────────────

    /**
     * @return array{repo: array, languages: array, readmeContent: string|null, tree: array, pr: array|null, prFiles: array, prComments: array, issue: array|null, issueComments: array}
     */
    private function fetchRaw(GitHubReference $ref): array
    {
        $http = $this->client();
        $base = "/repos/{$ref->owner}/{$ref->repo}";

        $repo = $http->get($base)->throw()->json();
        $languages = $http->get("$base/languages")->throw()->json();
        $readmeResponse = $http->get("$base/readme");
        if (! $readmeResponse->successful() && $readmeResponse->status() !== 404) {
            $readmeResponse->throw();
        }
        $readmeContent = $readmeResponse->successful() ? base64_decode($readmeResponse->json('content', '')) : null;

        $tree = $http->get("$base/git/trees/{$repo['default_branch']}", ['recursive' => 1])->throw()->json('tree', []);

        $pr = null;
        $prFiles = [];
        $prComments = [];
        $issue = null;
        $issueComments = [];

        if ($ref->type === 'pull_request') {
            $pr = $http->get("$base/pulls/{$ref->number}")->throw()->json();
            $prFiles = $http->get("$base/pulls/{$ref->number}/files", ['per_page' => 50])->throw()->json();
            $prComments = $http->get("$base/issues/{$ref->number}/comments", ['per_page' => 30])->throw()->json();
        } elseif ($ref->type === 'issue') {
            $issue = $http->get("$base/issues/{$ref->number}")->throw()->json();
            $issueComments = $http->get("$base/issues/{$ref->number}/comments", ['per_page' => 30])->throw()->json();
        }

        return compact('repo', 'languages', 'readmeContent', 'tree', 'pr', 'prFiles', 'prComments', 'issue', 'issueComments');
    }

    private function mapRequestException(RequestException $e, GitHubReference $ref): RuntimeException
    {
        $status = $e->response?->status();
        $fullName = "{$ref->owner}/{$ref->repo}";

        if ($status === 404) {
            return match ($ref->type) {
                'pull_request' => new RuntimeException("Pull request #{$ref->number} was not found in {$fullName}."),
                'issue' => new RuntimeException("Issue #{$ref->number} was not found in {$fullName}."),
                default => new RuntimeException("Repository {$fullName} was not found or is private."),
            };
        }

        if ($status === 403) {
            return new RuntimeException('GitHub API rate limit or access denied. Configure GITHUB_TOKEN for higher limits, or try again later.');
        }

        if ($status === 401) {
            return new RuntimeException('GitHub authentication failed. Check GITHUB_TOKEN.');
        }

        return new RuntimeException('GitHub API request failed'.($status ? " (HTTP {$status})" : '').'.');
    }

    private function client(): PendingRequest
    {
        $http = Http::baseUrl('https://api.github.com')
            ->acceptJson()
            ->withUserAgent('ai-flow')
            ->timeout(15)
            ->retry(2, 200, null, false);

        if ($token = config('services.github.token')) {
            $http = $http->withToken($token);
        }

        return $http;
    }
}
