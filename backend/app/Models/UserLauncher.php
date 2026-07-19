<?php

namespace App\Models;

use Database\Factories\UserLauncherFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserLauncher extends Model
{
    /** @use HasFactory<UserLauncherFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'slug',
        'name',
        'description',
        'prompt_template',
        'input_type',
        'output_schema',
    ];

    protected function casts(): array
    {
        return [
            'output_schema' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(Run::class, 'user_launcher_id');
    }
}
