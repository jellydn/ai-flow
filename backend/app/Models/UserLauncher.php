<?php

namespace App\Models;

use App\Contracts\LauncherSource;
use Database\Factories\UserLauncherFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserLauncher extends Model implements LauncherSource
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPromptTemplate(): string
    {
        return $this->prompt_template;
    }

    public function getInputType(): string
    {
        return $this->input_type;
    }

    public function getOutputSchema(): array
    {
        return $this->output_schema;
    }

    public function isBuiltIn(): bool
    {
        return false;
    }
}
