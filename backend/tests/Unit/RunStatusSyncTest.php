<?php

namespace Tests\Unit;

use App\Models\Run;
use Tests\TestCase;

/**
 * Guards against drift between the backend Run::STATUSES constant and the
 * frontend RunStatus type (CONCERNS #1).
 *
 * The frontend types/api.ts defines:
 *   export type RunStatus = "queued" | "running" | "completed" | "failed";
 *
 * If either side is updated without the other, this test will fail, forcing
 * the developer to update both sides in the same commit.
 */
class RunStatusSyncTest extends TestCase
{
    public function test_run_statuses_match_expected_set(): void
    {
        $expected = ['queued', 'running', 'completed', 'failed'];

        $this->assertSame($expected, Run::STATUSES);
    }

    public function test_run_terminal_statuses_are_subset_of_statuses(): void
    {
        foreach (Run::TERMINAL_STATUSES as $terminal) {
            $this->assertContains(
                $terminal,
                Run::STATUSES,
                "Terminal status '{$terminal}' is not in Run::STATUSES — frontend RunStatus type must include it.",
            );
        }
    }

    public function test_run_statuses_are_unique(): void
    {
        $this->assertSame(
            count(Run::STATUSES),
            count(array_unique(Run::STATUSES)),
            'Run::STATUSES contains duplicate values.',
        );
    }
}
