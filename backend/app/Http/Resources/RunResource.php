<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'launcher' => $this->launcher?->slug, 'input' => $this->input, 'status' => $this->status, 'progress' => $this->progress ?? [], 'result' => $this->result, 'error' => $this->error, 'started_at' => $this->started_at, 'completed_at' => $this->completed_at];
    }
}
