<?php

namespace Tests\Unit;

use App\Data\GitHubReference;
use App\Services\GitHubContextAssembler;
use PHPUnit\Framework\TestCase;

class GitHubContextAssemblerTest extends TestCase
{
    public function test_assembles_repository_context(): void
    {
        $assembler = new GitHubContextAssembler;
        $ref = new GitHubReference('a', 'b', 'repository');
        $raw = [
            'repo' => ['name' => 'b', 'full_name' => 'a/b', 'description' => 'A repo', 'default_branch' => 'main'],
            'languages' => ['PHP' => 5000],
            'readmeContent' => '# Hello',
            'tree' => [['path' => 'src/app.php'], ['path' => 'tests/Test.php']],
            'pr' => null, 'prFiles' => [], 'prComments' => [],
            'issue' => null, 'issueComments' => [],
        ];

        $context = $assembler->assemble($ref, $raw);

        $this->assertSame(['owner' => 'a', 'repo' => 'b', 'type' => 'repository', 'number' => null], $context['reference']);
        $this->assertSame('b', $context['repository']['name']);
        $this->assertSame('a/b', $context['repository']['full_name']);
        $this->assertSame('A repo', $context['repository']['description']);
        $this->assertSame('main', $context['repository']['default_branch']);
        $this->assertSame(['PHP' => 5000], $context['repository']['languages']);
        $this->assertSame('# Hello', $context['repository']['readme']);
        $this->assertSame(['src/app.php', 'tests/Test.php'], $context['repository']['file_tree']);
        $this->assertArrayNotHasKey('pull_request', $context);
        $this->assertArrayNotHasKey('changed_files', $context);
        $this->assertArrayNotHasKey('issue', $context);
        $this->assertArrayNotHasKey('comments', $context);
    }

    public function test_assembles_pull_request_context(): void
    {
        $assembler = new GitHubContextAssembler;
        $ref = new GitHubReference('a', 'b', 'pull_request', 1);
        $raw = [
            'repo' => ['name' => 'b', 'full_name' => 'a/b', 'description' => null, 'default_branch' => 'main'],
            'languages' => [],
            'readmeContent' => null,
            'tree' => [],
            'pr' => [
                'number' => 1,
                'title' => 'Fix bug',
                'body' => 'This fixes the issue.',
                'state' => 'open',
                'user' => ['login' => 'dev'],
                'changed_files' => 3,
            ],
            'prFiles' => [
                ['filename' => 'src/a.php', 'status' => 'modified', 'patch' => '@@ -1 +1 @@'],
                ['filename' => 'src/b.php', 'status' => 'added', 'patch' => '@@ -0,0 +1 @@'],
            ],
            'prComments' => [
                ['user' => ['login' => 'reviewer'], 'body' => 'LGTM'],
            ],
            'issue' => null, 'issueComments' => [],
        ];

        $context = $assembler->assemble($ref, $raw);

        $this->assertSame('pull_request', $context['reference']['type']);
        $this->assertSame(1, $context['pull_request']['number']);
        $this->assertSame('Fix bug', $context['pull_request']['title']);
        $this->assertSame('This fixes the issue.', $context['pull_request']['description']);
        $this->assertSame('open', $context['pull_request']['state']);
        $this->assertSame('dev', $context['pull_request']['author']);
        $this->assertSame(3, $context['pull_request']['changed_files']);
        $this->assertCount(2, $context['changed_files']);
        $this->assertSame('src/a.php', $context['changed_files'][0]['name']);
        $this->assertSame('modified', $context['changed_files'][0]['status']);
        $this->assertCount(1, $context['comments']);
        $this->assertSame('reviewer', $context['comments'][0]['author']);
        $this->assertSame('LGTM', $context['comments'][0]['body']);
    }

    public function test_assembles_issue_context(): void
    {
        $assembler = new GitHubContextAssembler;
        $ref = new GitHubReference('a', 'b', 'issue', 42);
        $raw = [
            'repo' => ['name' => 'b', 'full_name' => 'a/b', 'description' => null, 'default_branch' => 'main'],
            'languages' => [],
            'readmeContent' => null,
            'tree' => [],
            'pr' => null, 'prFiles' => [], 'prComments' => [],
            'issue' => [
                'number' => 42,
                'title' => 'Report bug',
                'body' => 'Something is broken.',
                'state' => 'open',
                'labels' => ['bug'],
                'user' => ['login' => 'reporter'],
                'extra_field' => 'should be stripped',
            ],
            'issueComments' => [
                ['user' => ['login' => 'helper'], 'body' => 'Try restarting.'],
            ],
        ];

        $context = $assembler->assemble($ref, $raw);

        $this->assertSame('issue', $context['reference']['type']);
        $this->assertSame(42, $context['issue']['number']);
        $this->assertSame('Report bug', $context['issue']['title']);
        $this->assertSame('Something is broken.', $context['issue']['body']);
        $this->assertSame('open', $context['issue']['state']);
        $this->assertArrayNotHasKey('extra_field', $context['issue']);
        $this->assertCount(1, $context['comments']);
        $this->assertSame('helper', $context['comments'][0]['user']);
        $this->assertSame('Try restarting.', $context['comments'][0]['body']);
    }

    public function test_null_readme_is_preserved(): void
    {
        $assembler = new GitHubContextAssembler;
        $ref = new GitHubReference('a', 'b', 'repository');
        $raw = [
            'repo' => ['name' => 'b', 'full_name' => 'a/b', 'description' => null, 'default_branch' => null],
            'languages' => [],
            'readmeContent' => null,
            'tree' => [],
            'pr' => null, 'prFiles' => [], 'prComments' => [],
            'issue' => null, 'issueComments' => [],
        ];

        $context = $assembler->assemble($ref, $raw);

        $this->assertNull($context['repository']['readme']);
    }

    public function test_missing_repo_fields_fall_back_to_reference(): void
    {
        $assembler = new GitHubContextAssembler;
        $ref = new GitHubReference('owner', 'repo-name', 'repository');
        $raw = [
            'repo' => [],
            'languages' => [],
            'readmeContent' => null,
            'tree' => [],
            'pr' => null, 'prFiles' => [], 'prComments' => [],
            'issue' => null, 'issueComments' => [],
        ];

        $context = $assembler->assemble($ref, $raw);

        $this->assertSame('repo-name', $context['repository']['name']);
        $this->assertSame('owner/repo-name', $context['repository']['full_name']);
        $this->assertNull($context['repository']['description']);
        $this->assertNull($context['repository']['default_branch']);
    }

    public function test_pr_files_capped_at_50(): void
    {
        $assembler = new GitHubContextAssembler;
        $ref = new GitHubReference('a', 'b', 'pull_request', 1);
        $files = array_fill(0, 60, ['filename' => 'f.php', 'status' => 'modified', 'patch' => 'diff']);
        $raw = [
            'repo' => ['name' => 'b', 'full_name' => 'a/b', 'description' => null, 'default_branch' => null],
            'languages' => [],
            'readmeContent' => null,
            'tree' => [],
            'pr' => ['number' => 1, 'title' => 'T', 'body' => '', 'state' => 'open', 'user' => ['login' => 'u'], 'changed_files' => 60],
            'prFiles' => $files,
            'prComments' => [],
            'issue' => null, 'issueComments' => [],
        ];

        $context = $assembler->assemble($ref, $raw);

        $this->assertCount(50, $context['changed_files']);
    }

    public function test_comments_capped_at_30(): void
    {
        $assembler = new GitHubContextAssembler;
        $ref = new GitHubReference('a', 'b', 'pull_request', 1);
        $comments = array_fill(0, 40, ['user' => ['login' => 'u'], 'body' => 'c']);
        $raw = [
            'repo' => ['name' => 'b', 'full_name' => 'a/b', 'description' => null, 'default_branch' => null],
            'languages' => [],
            'readmeContent' => null,
            'tree' => [],
            'pr' => ['number' => 1, 'title' => 'T', 'body' => '', 'state' => 'open', 'user' => ['login' => 'u'], 'changed_files' => 1],
            'prFiles' => [],
            'prComments' => $comments,
            'issue' => null, 'issueComments' => [],
        ];

        $context = $assembler->assemble($ref, $raw);

        $this->assertCount(30, $context['comments']);
    }
}
