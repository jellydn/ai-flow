<?php

namespace App\Launchers;

class PlanIssueLauncher extends BaseLauncher
{
    public static function metadata(): array
    {
        return static::make('plan-issue', 'Plan GitHub Issue', 'Turn a GitHub issue into an actionable implementation plan.', 'issue', 'Create an implementation plan for this issue, including risks, sequence, and tests. Use only the supplied GitHub context.');
    }
}
