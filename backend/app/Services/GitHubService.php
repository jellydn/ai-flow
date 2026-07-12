<?php

namespace App\Services;

use App\Data\GitHubReference;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class GitHubService
{
    public function __construct(
        private GitHubContextFetcher $fetcher,
        private GitHubContextAssembler $assembler,
    ) {}

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

    public function context(string $url): array
    {
        $ref = $this->parse($url);
        $key = 'github:'.sha1($url);

        return Cache::remember($key, now()->addMinutes(10), function () use ($ref) {
            $raw = $this->fetcher->fetch($ref);

            return $this->assembler->assemble($ref, $raw);
        });
    }
}
