<?php

namespace App\Services;

use App\Http\Resources\RunResource;
use App\Models\Run;
use Generator;
use Illuminate\Http\StreamedEvent;

class RunStreamer
{
    /**
     * Stream run progress as Server-Sent Events.
     *
     * Yields StreamedEvent instances for progress/completed/failed states.
     * Runs for up to $deadlineSeconds, polling the database every second.
     *
     * @return Generator<StreamedEvent>
     */
    public function stream(Run $run, int $deadlineSeconds = 55, int $pollIntervalMicroseconds = 1_000_000): Generator
    {
        $last = null;
        $deadline = microtime(true) + $deadlineSeconds;

        while (microtime(true) < $deadline && ! connection_aborted()) {
            $run->refresh();
            $snapshot = (new RunResource($run->loadMissing('launcher')))->resolve();
            $encoded = json_encode($snapshot);

            if ($encoded !== $last) {
                yield new StreamedEvent(event: 'progress', data: $encoded);
                $last = $encoded;
            }

            if (in_array($run->status, ['completed', 'failed'], true)) {
                yield new StreamedEvent(event: $run->status, data: $encoded);

                break;
            }

            usleep($pollIntervalMicroseconds);
        }
    }
}
