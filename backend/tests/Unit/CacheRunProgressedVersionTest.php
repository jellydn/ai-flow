<?php

namespace Tests\Unit;

use App\Events\RunProgressed;
use App\Listeners\CacheRunProgressedVersion;
use App\Models\Launcher;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheRunProgressedVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_stores_version_in_cache_on_run_progressed(): void
    {
        $this->seed();
        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
            'source_url' => 'https://github.com/a/b',
            'input' => ['source_url' => 'https://github.com/a/b'],
            'status' => 'running',
            'progress' => ['Fetching repository'],
        ]);

        $listener = new CacheRunProgressedVersion;
        $listener->handle(new RunProgressed($run));

        $this->assertNotNull(Cache::get(CacheRunProgressedVersion::versionKey($run->id)));
    }

    public function test_version_advances_on_subsequent_progress_events(): void
    {
        $this->seed();
        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
            'source_url' => 'https://github.com/a/b',
            'input' => ['source_url' => 'https://github.com/a/b'],
            'status' => 'running',
            'progress' => ['Fetching repository'],
        ]);

        $listener = new CacheRunProgressedVersion;
        $listener->handle(new RunProgressed($run));
        $firstVersion = Cache::get(CacheRunProgressedVersion::versionKey($run->id));

        usleep(10); // ensure microtime advances

        $run->update(['progress' => ['Fetching repository', 'Running AI analysis']]);
        $listener->handle(new RunProgressed($run->fresh()));
        $secondVersion = Cache::get(CacheRunProgressedVersion::versionKey($run->id));

        $this->assertGreaterThan($firstVersion, $secondVersion);
    }

    public function test_cache_ttl_is_two_minutes(): void
    {
        $this->seed();
        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
            'source_url' => 'https://github.com/a/b',
            'input' => ['source_url' => 'https://github.com/a/b'],
            'status' => 'running',
            'progress' => ['Fetching repository'],
        ]);

        $listener = new CacheRunProgressedVersion;
        $listener->handle(new RunProgressed($run));

        $now = now();
        // The TTL is set to now()->addMinutes(2) so the value should not
        // expire before $now->addMinutes(2). Travel 119 seconds forward
        // and assert the key is still there.
        $this->travelTo($now->addSeconds(119));
        $this->assertNotNull(Cache::get(CacheRunProgressedVersion::versionKey($run->id)));

        // After 121 seconds it should be gone.
        $this->travelTo($now->addSeconds(121));
        $this->assertNull(Cache::get(CacheRunProgressedVersion::versionKey($run->id)));
    }
}
