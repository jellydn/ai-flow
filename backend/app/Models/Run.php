<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Run extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'launcher_id',
        'user_id',
        'provider_credential_id',
        'provider',
        'model',
        'source_url',
        'status',
        'progress',
        'input',
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
}
