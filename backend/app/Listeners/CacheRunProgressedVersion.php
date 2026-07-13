<?php

namespace App\Listeners;

use App\Events\RunProgressed;
use Illuminate\Support\Facades\Cache;

class CacheRunProgressedVersion
{
    /** Cache key prefix for run progress versioning. Shared with RunStreamer. */
    public const KEY_PREFIX = 'run:version:';

    /**
     * Store a version timestamp in the cache so the SSE streamer
     * can detect progress without polling the database every second.
     */
    public function handle(RunProgressed $event): void
    {
        Cache::put(
            self::versionKey($event->run->id),
            microtime(true),
            now()->addMinutes(2),
        );
    }

    public static function versionKey(string $runId): string
    {
        return self::KEY_PREFIX.$runId;
    }
}
