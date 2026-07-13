<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = $request->user() && $this->isOwnedBy($request->user());

        return [
            'id' => $this->id,
            'launcher' => $this->launcher?->slug,
            'input' => $this->input,
            'status' => $this->status,
            'progress' => $this->progress ?? [],
            'result' => $this->result,
            'provider' => $this->when($isOwner, $this->provider),
            'model' => $this->when($isOwner, $this->model),
            'error' => $this->when($this->status === 'failed', $this->error),
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
        ];
    }
}
