<?php

namespace App\Models;

use App\Events\RunProgressed;
use Database\Factories\RunFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Throwable;

class Run extends Model
{
    /** @use HasFactory<RunFactory> */
    use HasFactory, HasUuids;

    // Keep in sync with the frontend status enum in backend/resources/ts/types/api.ts (RunStatus)
    // and the runtime guard isRunStatus in backend/resources/ts/services/run.ts.
    public const STATUSES = ['queued', 'running', 'completed', 'failed'];

    public const TERMINAL_STATUSES = ['completed', 'failed'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'launcher_id',
        'user_id',
        'provider_credential_id',
        'provider',
        'model',
        'source_url',
        'repo_slug',
        'repo_type',
        'status',
        'progress',
        'input',
        'prompt_snapshot',
        'source_context',
        'result',
        'error',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'array',
            'input' => 'array',
            'source_context' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function launcher(): BelongsTo
    {
        return $this->belongsTo(Launcher::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function providerCredential(): BelongsTo
    {
        return $this->belongsTo(ProviderCredential::class);
    }

    /**
     * Whether this run is owned by a specific authenticated user
     * (as opposed to being an anonymous public run).
     */
    public function isOwned(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Whether this run is owned by the given user.
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Transition this run to the failed state.
     *
     * Single owner of the run-failure lifecycle: sets status, error,
     * clears source_context, sets completed_at, logs the failure, and
     * dispatches RunProgressed. Called by ExecuteLauncherJob,
     * RunExecutor, and ReapStuckRuns.
     */
    public function markFailed(string $message, ?Throwable $e = null, string $logContext = 'Launcher run failed'): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $message,
            'source_context' => null,
            'completed_at' => now(),
        ]);

        Log::error($logContext, [
            'run_id' => $this->id,
            'exception' => $e ? get_class($e) : null,
            'exception_message' => $e?->getMessage(),
        ]);

        RunProgressed::dispatch($this->fresh());
    }
}
