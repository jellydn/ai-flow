<?php

namespace App\Console\Commands;

use App\Models\Run;
use Illuminate\Console\Command;

class ReapStuckRuns extends Command
{
    protected $signature = 'app:reap-stuck-runs {--ttl=180 : Seconds a run may be stuck in "running" before it is reaped}';

    protected $description = 'Transition orphaned "running" runs to "failed" after the TTL expires';

    public function handle(): int
    {
        $ttl = (int) $this->option('ttl');
        $cutoff = now()->subSeconds($ttl);

        $stuck = Run::query()
            ->where('status', 'running')
            ->where('started_at', '<', $cutoff)
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck runs found.');

            return self::SUCCESS;
        }

        /** @var Run $run */
        foreach ($stuck as $run) {
            $run->markFailed('Run timed out.', logContext: 'Reaped stuck run');

            $this->warn("Reaped stuck run: {$run->id} (started {$run->started_at?->diffForHumans()}, ttl={$ttl}s)");
        }

        $this->info("Reaped {$stuck->count()} stuck run(s).");

        return self::SUCCESS;
    }
}
