<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Launcher extends Model
{
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
