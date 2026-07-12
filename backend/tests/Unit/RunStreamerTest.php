<?php

namespace Tests\Unit;

use App\Models\Launcher;
use App\Models\Run;
use App\Services\RunStreamer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\StreamedEvent;
use Tests\TestCase;

class RunStreamerTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_run_yields_progress_and_completed_events(): void
    {
        $this->seed();
        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
            'source_url' => 'https://github.com/a/b',
            'input' => ['source_url' => 'https://github.com/a/b'],
            'status' => 'completed',
            'progress' => ['Fetched', 'Done'],
            'result' => ['summary' => 'Ready', 'risk' => 'low', 'findings' => [], 'verification_steps' => []],
            'completed_at' => now(),
        ]);

        $streamer = new RunStreamer;
        $events = iterator_to_array($streamer->stream($run, 2, 10_000));

        $this->assertGreaterThanOrEqual(2, count($events));

        $progressEvent = $events[0];
        $this->assertInstanceOf(StreamedEvent::class, $progressEvent);
        $this->assertSame('progress', $progressEvent->event);

        $terminalEvent = $events[count($events) - 1];
        $this->assertSame('completed', $terminalEvent->event);
        $this->assertStringContainsString('"status":"completed"', $terminalEvent->data);
    }

    public function test_failed_run_yields_progress_and_failed_events(): void
    {
        $this->seed();
        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
            'source_url' => 'https://github.com/a/b',
            'input' => ['source_url' => 'https://github.com/a/b'],
            'status' => 'failed',
            'progress' => ['Started'],
            'error' => 'Something went wrong.',
            'completed_at' => now(),
        ]);

        $streamer = new RunStreamer;
        $events = iterator_to_array($streamer->stream($run, 2, 10_000));

        $this->assertGreaterThanOrEqual(2, count($events));

        $terminalEvent = $events[count($events) - 1];
        $this->assertSame('failed', $terminalEvent->event);
        $this->assertStringContainsString('"error":"Something went wrong."', $terminalEvent->data);
    }

    public function test_running_run_yields_progress_event_and_does_not_emit_terminal(): void
    {
        $this->seed();
        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
            'source_url' => 'https://github.com/a/b',
            'input' => ['source_url' => 'https://github.com/a/b'],
            'status' => 'running',
            'progress' => ['Fetching repository'],
            'started_at' => now(),
        ]);

        $streamer = new RunStreamer;
        $events = iterator_to_array($streamer->stream($run, 1, 10_000));

        $this->assertGreaterThanOrEqual(1, count($events));

        $firstEvent = $events[0];
        $this->assertSame('progress', $firstEvent->event);
        $this->assertStringContainsString('"status":"running"', $firstEvent->data);

        $terminalTypes = array_filter($events, fn (StreamedEvent $e) => in_array($e->event, ['completed', 'failed'], true));
        $this->assertEmpty($terminalTypes);
    }

    public function test_unchanged_snapshot_is_not_re_yielded(): void
    {
        $this->seed();
        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
            'source_url' => 'https://github.com/a/b',
            'input' => ['source_url' => 'https://github.com/a/b'],
            'status' => 'completed',
            'progress' => ['Done'],
            'result' => ['summary' => 'Done', 'risk' => 'low', 'findings' => [], 'verification_steps' => []],
            'completed_at' => now(),
        ]);

        $streamer = new RunStreamer;

        // With a very short deadline (0s), the loop runs once, yields progress + completed, then exits.
        // The completed run doesn't change between iterations so there should be exactly 2 events.
        $events = iterator_to_array($streamer->stream($run, 1, 10_000));

        $progressCount = count(array_filter($events, fn (StreamedEvent $e) => $e->event === 'progress'));
        $this->assertSame(1, $progressCount, 'Only one progress event should be yielded for an unchanged snapshot.');
    }
}
