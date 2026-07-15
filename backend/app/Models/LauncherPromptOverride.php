<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LauncherPromptOverride extends Model
{
    protected $fillable = [
        'user_id',
        'launcher_id',
        'prompt_template',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function launcher(): BelongsTo
    {
        return $this->belongsTo(Launcher::class);
    }
}
