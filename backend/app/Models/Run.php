<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Run extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['launcher_id', 'source_url', 'status', 'progress', 'input', 'source_context', 'result', 'error', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['progress' => 'array', 'input' => 'array', 'source_context' => 'array', 'result' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function launcher()
    {
        return $this->belongsTo(Launcher::class);
    }
}
