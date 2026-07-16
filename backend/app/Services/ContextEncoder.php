<?php

namespace App\Services;

class ContextEncoder
{
    // Budget constants live in ContextBudget — single source of truth.

    public function encode(array $context): string
    {
        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($encoded) <= ContextBudget::MAX_CONTEXT_BYTES) {
            return $encoded;
        }

        $bounded = $context;
        $bounded['truncated'] = true;
        if (isset($bounded['repository'])) {
            $bounded['repository']['readme'] = mb_substr($bounded['repository']['readme'] ?? '', 0, ContextBudget::BUDGET_README_LIMIT);
            $bounded['repository']['file_tree'] = array_slice($bounded['repository']['file_tree'] ?? [], 0, ContextBudget::BUDGET_FILE_TREE_LIMIT);
        }
        $bounded['changed_files'] = array_map(function (array $file): array {
            $file['diff'] = mb_substr($file['diff'] ?? '', 0, ContextBudget::BUDGET_DIFF_LIMIT);

            return $file;
        }, array_slice($bounded['changed_files'] ?? [], 0, ContextBudget::BUDGET_CHANGED_FILES_LIMIT));
        $bounded['comments'] = array_map(function (array $comment): array {
            if (isset($comment['body'])) {
                $comment['body'] = mb_substr($comment['body'], 0, ContextBudget::BUDGET_COMMENT_BODY_LIMIT);
            }

            return $comment;
        }, array_slice($bounded['comments'] ?? [], 0, ContextBudget::BUDGET_COMMENTS_LIMIT));

        $encoded = json_encode($bounded, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($encoded) <= ContextBudget::MAX_CONTEXT_BYTES) {
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
