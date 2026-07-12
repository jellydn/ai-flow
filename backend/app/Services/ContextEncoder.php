<?php

namespace App\Services;

class ContextEncoder
{
    private const MAX_CONTEXT_BYTES = 120_000;

    public function encode(array $context): string
    {
        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($encoded) <= self::MAX_CONTEXT_BYTES) {
            return $encoded;
        }

        $bounded = $context;
        $bounded['truncated'] = true;
        if (isset($bounded['repository'])) {
            $bounded['repository']['readme'] = mb_substr($bounded['repository']['readme'] ?? '', 0, 10_000);
            $bounded['repository']['file_tree'] = array_slice($bounded['repository']['file_tree'] ?? [], 0, 250);
        }
        $bounded['changed_files'] = array_map(function (array $file): array {
            $file['diff'] = mb_substr($file['diff'] ?? '', 0, 1_000);

            return $file;
        }, array_slice($bounded['changed_files'] ?? [], 0, 30));
        $bounded['comments'] = array_map(function (array $comment): array {
            if (isset($comment['body'])) {
                $comment['body'] = mb_substr($comment['body'], 0, 1_000);
            }

            return $comment;
        }, array_slice($bounded['comments'] ?? [], 0, 10));

        $encoded = json_encode($bounded, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($encoded) <= self::MAX_CONTEXT_BYTES) {
            return $encoded;
        }

        return json_encode([
            'reference' => $bounded['reference'] ?? null,
            'repository' => array_intersect_key($bounded['repository'] ?? [], array_flip(['name', 'full_name', 'description', 'default_branch', 'languages'])),
            'issue' => $bounded['issue'] ?? null,
            'pull_request' => $bounded['pull_request'] ?? null,
            'truncated' => true,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
