<?php

namespace App\Services;

use App\Data\GitHubReference;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GitHubContextFetcher
{
    /**
     * Fetch raw GitHub data for a parsed reference.
     *
     * @return array{repo: array, languages: array, readmeContent: string|null, tree: array, pr: array|null, prFiles: array, prComments: array, issue: array|null, issueComments: array}
     */
    public function fetch(GitHubReference $ref): array
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

    private function client(): PendingRequest
    {
        $http = Http::baseUrl('https://api.github.com')
            ->acceptJson()
            ->withUserAgent('ai-launcher')
            ->timeout(15)
            ->retry(2, 200, null, false);

        if ($token = config('services.github.token')) {
            $http = $http->withToken($token);
        }

        return $http;
    }
}
