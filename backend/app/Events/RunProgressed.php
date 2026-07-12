<?php

namespace App\Events;

use App\Models\Run;
use Illuminate\Foundation\Events\Dispatchable;

class RunProgressed
{
    use Dispatchable;

    public function __construct(public Run $run) {}
}
