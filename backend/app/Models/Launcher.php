<?php

namespace App\Models;

use Database\Factories\LauncherFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Launcher extends Model
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
}
