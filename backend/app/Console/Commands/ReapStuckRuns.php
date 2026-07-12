<?php

namespace App\Console\Commands;

use App\Events\RunProgressed;
use App\Models\Run;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
            $run->update([
                'status' => 'failed',
                'error' => 'Run timed out.',
                'source_context' => null,
                'completed_at' => now(),
            ]);

            RunProgressed::dispatch($run->fresh());

            Log::warning('Reaped stuck run', [
                'run_id' => $run->id,
                'started_at' => $run->started_at,
                'ttl_seconds' => $ttl,
            ]);

            $this->warn("Reaped stuck run: {$run->id}");
        }

        $this->info("Reaped {$stuck->count()} stuck run(s).");

        return self::SUCCESS;
    }
}
