<?php

namespace App\Data;

readonly class GitHubReference
{
    public function __construct(
        public string $owner,
        public string $repo,
        public string $type,
        public ?int $number = null,
    ) {}

    /**
     * @return array{owner: string, repo: string, type: string, number: int|null}
     */
    public function toArray(): array
    {
        return [
            'owner' => $this->owner,
            'repo' => $this->repo,
            'type' => $this->type,
            'number' => $this->number,
        ];
    }
}
