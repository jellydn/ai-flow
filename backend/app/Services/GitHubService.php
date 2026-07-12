<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class GitHubService
{
    public function parse(string $url): array
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

        return ['owner' => $parts[0], 'repo' => preg_replace('/\.git$/', '', $parts[1]), 'type' => $type, 'number' => $number];
    }

    public function context(string $url): array
    {
        $ref = $this->parse($url);
        $key = 'github:'.sha1($url);

        return Cache::remember($key, now()->addMinutes(10), function () use ($ref) {
            $http = Http::baseUrl('https://api.github.com')->acceptJson()->withUserAgent('ai-launcher')->timeout(15)->retry(2, 200);
            if ($token = config('services.github.token')) {
                $http = $http->withToken($token);
            }
            $base = "/repos/{$ref['owner']}/{$ref['repo']}";
            $repo = $http->get($base)->throw()->json();
            $languages = $http->get("$base/languages")->throw()->json();
            $readme = $http->get("$base/readme");
            if (! $readme->successful() && $readme->status() !== 404) {
                $readme->throw();
            }

            $tree = $http->get("$base/git/trees/{$repo['default_branch']}", ['recursive' => 1])->throw()->json('tree', []);
            $context = [
                'reference' => $ref,
                'repository' => [
                    'name' => $repo['name'] ?? $ref['repo'],
                    'full_name' => $repo['full_name'] ?? "{$ref['owner']}/{$ref['repo']}",
                    'description' => $repo['description'] ?? null,
                    'default_branch' => $repo['default_branch'] ?? null,
                    'languages' => $languages,
                    'readme' => $readme->successful() ? mb_substr(base64_decode($readme->json('content', '')), 0, 20000) : null,
                    'file_tree' => array_slice(array_column($tree, 'path'), 0, 500),
                ],
            ];
            if ($ref['type'] === 'pull_request') {
                $pr = $http->get("$base/pulls/{$ref['number']}")->throw()->json();
                $files = $http->get("$base/pulls/{$ref['number']}/files", ['per_page' => 50])->throw()->json();
                $comments = $http->get("$base/issues/{$ref['number']}/comments", ['per_page' => 30])->throw()->json();
                $context['pull_request'] = ['number' => $pr['number'], 'title' => $pr['title'], 'description' => $pr['body'] ?? '', 'state' => $pr['state'], 'author' => $pr['user']['login'] ?? null, 'changed_files' => $pr['changed_files'] ?? count($files)];
                $context['changed_files'] = array_map(fn ($f) => ['name' => $f['filename'], 'status' => $f['status'], 'diff' => mb_substr($f['patch'] ?? '', 0, 4000)], array_slice($files, 0, 50));
                $context['comments'] = array_map(fn ($c) => ['author' => $c['user']['login'] ?? null, 'body' => mb_substr($c['body'] ?? '', 0, 3000)], array_slice($comments, 0, 30));
            } elseif ($ref['type'] === 'issue') {
                $issue = $http->get("$base/issues/{$ref['number']}")->throw()->json();
                $comments = $http->get("$base/issues/{$ref['number']}/comments", ['per_page' => 30])->throw()->json();
                $context['issue'] = array_intersect_key($issue, array_flip(['number', 'title', 'body', 'state', 'labels', 'user']));
                $context['comments'] = array_map(
                    fn ($comment) => [
                        'user' => $comment['user']['login'] ?? null,
                        'body' => mb_substr($comment['body'] ?? '', 0, 3000),
                    ],
                    array_slice($comments, 0, 30),
                );
            }

            return $context;
        });
    }
}
