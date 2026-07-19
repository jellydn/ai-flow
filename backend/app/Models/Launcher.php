<?php

namespace App\Models;

use App\Contracts\LauncherSource;
use Database\Factories\LauncherFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Launcher extends Model implements LauncherSource
{
    /** @use HasFactory<LauncherFactory> */
    use HasFactory;

    protected $fillable = ['slug', 'name', 'description', 'prompt_template', 'input_type', 'output_schema', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'output_schema' => 'array'];
    }

    public function runs()
    {
        return $this->hasMany(Run::class);
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
        return true;
    }
}
