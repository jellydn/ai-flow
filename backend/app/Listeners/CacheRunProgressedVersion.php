<?php

namespace App\Listeners;

use App\Events\RunProgressed;
use Illuminate\Support\Facades\Cache;

class CacheRunProgressedVersion
{
    /**
     * Store a version timestamp in the cache so the SSE streamer
     * can detect progress without polling the database every second.
     */
    public function handle(RunProgressed $event): void
    {
        Cache::put(
            "run:{$event->run->id}:version",
            microtime(true),
            now()->addMinutes(2),
        );
    }
}
