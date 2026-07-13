<?php

namespace App\Services;

use App\Http\Resources\RunResource;
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
        $versionKey = "run:{$run->id}:version";

        while (microtime(true) < $deadline && ! connection_aborted()) {
            $version = Cache::get($versionKey);

            // Cache unavailable (e.g. array driver in tests) — fall back to
            // unconditional DB refresh so existing behaviour is preserved.
            if ($version === null) {
                $encoded = $this->refreshAndEncode($run);

                if ($encoded !== $lastEncoded) {
                    yield new StreamedEvent(event: 'progress', data: $encoded);
                    $lastEncoded = $encoded;
                }

                if (in_array($run->status, ['completed', 'failed'], true)) {
                    yield new StreamedEvent(event: $run->status, data: $encoded);

                    break;
                }

                usleep($pollIntervalMicroseconds);

                continue;
            }

            if ($version === $lastVersion) {
                usleep($pollIntervalMicroseconds);

                continue;
            }

            $lastVersion = $version;
            $encoded = $this->refreshAndEncode($run);

            if ($encoded !== $lastEncoded) {
                yield new StreamedEvent(event: 'progress', data: $encoded);
                $lastEncoded = $encoded;
            }

            if (in_array($run->status, ['completed', 'failed'], true)) {
                yield new StreamedEvent(event: $run->status, data: $encoded);

                break;
            }

            usleep($pollIntervalMicroseconds);
        }
    }

    /**
     * Refresh the run from the database and return its resolved JSON snapshot.
     */
    private function refreshAndEncode(Run $run): string
    {
        $run->refresh();

        return json_encode((new RunResource($run->loadMissing('launcher')))->resolve());
    }
}
