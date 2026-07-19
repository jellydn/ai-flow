<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserHiddenLauncher extends Model
{
    protected $fillable = [
        'user_id',
        'launcher_id',
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
