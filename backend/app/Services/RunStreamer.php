<?php

namespace App\Services;

use App\Http\Resources\RunResource;
use App\Listeners\CacheRunProgressedVersion;
use App\Models\Run;
use Generator;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\Cache;

class RunStreamer
{
    /**
     * Stream run progress as Server-Sent Events.
     *
     * Checks a cache version key (set by CacheRunProgressedVersion listener)
     * before hitting the database. When the version is unchanged the loop
     * sleeps without a DB query; when the version bumps a fresh snapshot is
     * fetched and yielded. Falls back to unconditional DB refresh when the
     * cache is unavailable (e.g. array driver in tests).
     *
     * @return Generator<StreamedEvent>
     */
    public function stream(Run $run, int $deadlineSeconds = 55, int $pollIntervalMicroseconds = 1_000_000): Generator
    {
        $lastEncoded = null;
        $lastVersion = null;
        $deadline = microtime(true) + $deadlineSeconds;
        $versionKey = CacheRunProgressedVersion::versionKey($run->id);

        while (microtime(true) < $deadline && ! connection_aborted()) {
            $version = Cache::get($versionKey);

            // Null version = cache unavailable (e.g. array driver in tests).
            // In that case, always refresh from DB to preserve existing behaviour.
            if (! $this->shouldRefresh($version, $lastVersion)) {
                usleep($pollIntervalMicroseconds);

                continue;
            }

            $lastVersion = $version;
            $encoded = $this->fetchSnapshot($run);

            if ($encoded !== $lastEncoded) {
                yield new StreamedEvent(event: 'progress', data: $encoded);
                $lastEncoded = $encoded;
            }

            if ($this->isTerminal($run)) {
                yield new StreamedEvent(event: $run->status, data: $encoded);

                break;
            }

            usleep($pollIntervalMicroseconds);
        }
    }

    /**
     * Decide whether to refresh the snapshot from the database.
     *
     * - null version (cache unavailable): always refresh (fallback path)
     * - version unchanged from last poll: skip DB query, just sleep
     * - version changed: refresh
     */
    private function shouldRefresh(mixed $version, mixed $lastVersion): bool
    {
        return $version === null || $version !== $lastVersion;
    }

    /**
     * Refresh the run from the database and return its resolved JSON snapshot.
     */
    private function fetchSnapshot(Run $run): string
    {
        $run->refresh();

        return json_encode((new RunResource($run->loadMissing('launcher')))->resolve());
    }

    private function isTerminal(Run $run): bool
    {
        return in_array($run->status, ['completed', 'failed'], true);
    }
}
