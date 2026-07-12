<?php

namespace Tests\Unit;

use App\Services\ContextEncoder;
use PHPUnit\Framework\TestCase;

class ContextEncoderTest extends TestCase
{
    public function test_small_context_passes_through_unchanged(): void
    {
        $encoder = new ContextEncoder;
        $context = ['repository' => ['full_name' => 'a/b', 'readme' => 'Small readme.']];

        $encoded = json_decode($encoder->encode($context), true);

        $this->assertArrayNotHasKey('truncated', $encoded);
        $this->assertSame('Small readme.', $encoded['repository']['readme']);
    }

    public function test_medium_context_triggers_bounded_tier(): void
    {
        $encoder = new ContextEncoder;
        $context = [
            'repository' => [
                'full_name' => 'a/b',
                'readme' => str_repeat('r', 15_000),
                'file_tree' => array_fill(0, 300, 'src/File.php'),
            ],
            'changed_files' => array_fill(0, 50, ['name' => 'f.php', 'status' => 'modified', 'diff' => str_repeat('d', 3_000)]),
            'comments' => array_fill(0, 20, ['author' => 'u', 'body' => str_repeat('c', 2_000)]),
        ];

        $encoded = json_decode($encoder->encode($context), true);

        $this->assertTrue($encoded['truncated']);
        $this->assertLessThanOrEqual(10_000, strlen($encoded['repository']['readme']));
        $this->assertCount(250, $encoded['repository']['file_tree']);
        $this->assertCount(30, $encoded['changed_files']);
        $this->assertCount(10, $encoded['comments']);
    }

    public function test_very_large_context_triggers_minimal_tier(): void
    {
        $encoder = new ContextEncoder;
        $context = [
            'reference' => ['owner' => 'a', 'repo' => 'b', 'type' => 'repository'],
            'repository' => [
                'name' => 'b',
                'full_name' => 'a/b',
                'description' => 'A test repo',
                'default_branch' => 'main',
                'languages' => ['PHP' => 80_000],
                'readme' => str_repeat('r', 300_000),
                'file_tree' => array_fill(0, 1000, '/'.str_repeat('x', 300)),
            ],
            'changed_files' => array_fill(0, 200, [
                'name' => str_repeat('f', 100).'.php',
                'status' => 'modified',
                'diff' => str_repeat('d', 4_000),
            ]),
            'comments' => array_fill(0, 200, [
                'author' => str_repeat('u', 50),
                'body' => str_repeat('c', 3_000),
            ]),
        ];

        $result = $encoder->encode($context);
        $encoded = json_decode($result, true);

        $this->assertTrue($encoded['truncated']);
        $this->assertArrayHasKey('reference', $encoded);
        $this->assertArrayHasKey('repository', $encoded);
        $this->assertArrayHasKey('name', $encoded['repository']);
        $this->assertArrayHasKey('full_name', $encoded['repository']);
        $this->assertArrayHasKey('description', $encoded['repository']);
        $this->assertArrayHasKey('default_branch', $encoded['repository']);
        $this->assertArrayHasKey('languages', $encoded['repository']);
        $this->assertArrayNotHasKey('readme', $encoded['repository']);
        $this->assertArrayNotHasKey('file_tree', $encoded['repository']);
        $this->assertArrayNotHasKey('changed_files', $encoded);
        $this->assertArrayNotHasKey('comments', $encoded);
    }

    public function test_empty_context_returns_valid_json(): void
    {
        $encoder = new ContextEncoder;

        $result = $encoder->encode([]);

        $this->assertSame('[]', $result);
    }

    public function test_context_without_repository_or_files_passes_through(): void
    {
        $encoder = new ContextEncoder;
        $context = ['custom_key' => 'custom_value'];

        $encoded = json_decode($encoder->encode($context), true);

        $this->assertArrayNotHasKey('truncated', $encoded);
        $this->assertSame('custom_value', $encoded['custom_key']);
    }

    public function test_pull_request_context_in_minimal_tier_retains_pr_key(): void
    {
        $encoder = new ContextEncoder;
        $context = [
            'reference' => ['owner' => 'a', 'repo' => 'b', 'type' => 'pull_request'],
            'repository' => [
                'name' => 'b',
                'full_name' => 'a/b',
                'description' => 'desc',
                'default_branch' => 'main',
                'languages' => ['PHP' => 500],
                'readme' => str_repeat('r', 60_000),
                'file_tree' => array_fill(0, 500, str_repeat('x', 300)),
            ],
            'pull_request' => ['number' => 1, 'title' => 'Fix bug'],
            'changed_files' => array_fill(0, 50, ['name' => 'f.php', 'status' => 'modified', 'diff' => str_repeat('d', 5_000)]),
            'comments' => array_fill(0, 30, ['author' => 'u', 'body' => str_repeat('c', 5_000)]),
        ];

        $encoded = json_decode($encoder->encode($context), true);

        $this->assertTrue($encoded['truncated']);
        $this->assertArrayHasKey('pull_request', $encoded);
        $this->assertSame(1, $encoded['pull_request']['number']);
    }

    public function test_issue_context_in_minimal_tier_retains_issue_key(): void
    {
        $encoder = new ContextEncoder;
        $context = [
            'reference' => ['owner' => 'a', 'repo' => 'b', 'type' => 'issue'],
            'repository' => [
                'name' => 'b',
                'full_name' => 'a/b',
                'description' => 'desc',
                'default_branch' => 'main',
                'languages' => ['PHP' => 500],
                'readme' => str_repeat('r', 60_000),
                'file_tree' => array_fill(0, 500, str_repeat('x', 300)),
            ],
            'issue' => ['number' => 42, 'title' => 'Report bug'],
            'comments' => array_fill(0, 30, ['author' => 'u', 'body' => str_repeat('c', 5_000)]),
        ];

        $encoded = json_decode($encoder->encode($context), true);

        $this->assertTrue($encoded['truncated']);
        $this->assertArrayHasKey('issue', $encoded);
        $this->assertSame(42, $encoded['issue']['number']);
    }

    public function test_bounded_tier_respects_encoding_size_limit(): void
    {
        $encoder = new ContextEncoder;
        $context = [
            'repository' => [
                'full_name' => 'a/b',
                'readme' => str_repeat('a', 11_000),
                'file_tree' => array_fill(0, 260, 'src/File.php'),
            ],
            'changed_files' => array_fill(0, 40, [
                'name' => 'f.php',
                'status' => 'modified',
                'diff' => str_repeat('d', 2_000),
            ]),
            'comments' => array_fill(0, 15, [
                'author' => 'u',
                'body' => str_repeat('c', 1_500),
            ]),
        ];

        $result = $encoder->encode($context);

        $this->assertLessThanOrEqual(120_000, strlen($result));
    }
}
