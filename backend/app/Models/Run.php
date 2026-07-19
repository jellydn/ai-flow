<?php

namespace App\Models;

use App\Contracts\LauncherSource;
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
        'user_launcher_id',
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
        'is_public',
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
            'is_public' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function launcher(): BelongsTo
    {
        return $this->belongsTo(Launcher::class);
    }

    public function userLauncher(): BelongsTo
    {
        return $this->belongsTo(UserLauncher::class, 'user_launcher_id');
    }

    /**
     * Return the effective launcher — either a built-in Launcher
     * or a user-created UserLauncher — depending on which FK is set.
     * Both implement App\Contracts\LauncherSource.
     */
    public function launcherSource(): ?LauncherSource
    {
        if ($this->user_launcher_id !== null) {
            return $this->userLauncher;
        }

        return $this->launcher;
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
     * Whether this run is public (viewable by anyone).
     */
    public function isPublic(): bool
    {
        return (bool) $this->is_public;
    }

    /**
     * Transition this run to the failed state.
     *
     * Single owner of the run-failure lifecycle: sets status, error,
     * clears source_context, sets completed_at, logs the failure, and
     * dispatches RunProgressed. Called by ExecuteLauncherJob,
     * RunExecutor, and ReapStuckRuns.
     *
     * @param  'error'|'warning'|'info'  $logLevel  PSR-3 log level.
     *                                              Use 'warning' for expected failures (user input errors,
     *                                              network blips) so Sentry ignores them. Default 'error'
     *                                              for operational failures that need attention.
     */
    public function markFailed(string $message, ?Throwable $e = null, string $logContext = 'Launcher run failed', string $logLevel = 'error'): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $message,
            'source_context' => null,
            'completed_at' => now(),
        ]);

        $context = [
            'run_id' => $this->id,
            'exception' => $e ? get_class($e) : null,
            'exception_message' => $e?->getMessage(),
        ];

        match ($logLevel) {
            'warning' => Log::warning($logContext, $context),
            'info' => Log::info($logContext, $context),
            default => Log::error($logContext, $context),
        };

        RunProgressed::dispatch($this->fresh());
    }
}
