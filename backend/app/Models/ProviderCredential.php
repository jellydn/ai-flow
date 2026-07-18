<?php

namespace App\Models;

use App\Security\CredentialCipher;
use Database\Factories\ProviderCredentialFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderCredential extends Model
{
    /** @use HasFactory<ProviderCredentialFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'provider',
        'label',
        'encrypted_api_key',
        'encrypted_base_url',
        'default_model',
        /**
         * Reserved for future per-provider configuration (e.g., organization
         * ID for OpenAI, project for Anthropic). A free-form JSON object
         * stored as JSONB in PostgreSQL / TEXT in SQLite. Currently unused
         * but exposed via the API for forward compatibility.
         */
        'metadata',
        'is_default',
        'last_verified_at',
        'last_used_at',
    ];

    protected $hidden = [
        'encrypted_api_key',
        'encrypted_base_url',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_default' => 'boolean',
            'last_verified_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $credential) {
            if ($credential->is_default) {
                static::where('user_id', $credential->user_id)
                    ->where('id', '!=', $credential->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Decrypt the API key for immediate use.
     *
     * The plaintext value must not be stored, logged, serialized,
     * or returned in API responses.
     */
    public function decryptApiKey(CredentialCipher $cipher): string
    {
        return $cipher->decrypt($this->encrypted_api_key);
    }

    /**
     * Return a masked representation of the API key for display.
     */
    public function maskedKey(CredentialCipher $cipher): string
    {
        return $cipher->mask($cipher->decrypt($this->encrypted_api_key));
    }
}
