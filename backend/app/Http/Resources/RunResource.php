<?php

namespace App\Http\Resources;

use App\Support\AiProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $registry = app(AiProviderRegistry::class);

        $launcherSlug = $this->launcher?->slug ?? $this->userLauncher?->slug;

        return [
            'id' => $this->id,
            'launcher' => $launcherSlug,
            'input' => $this->input,
            'status' => $this->status,
            'progress' => $this->progress ?? [],
            'result' => $this->result,
            'provider' => $this->provider,
            'provider_label' => $registry->displayName($this->provider),
            'model' => $this->model,
            'is_public' => $this->is_public,
            'error' => $this->when($this->status === 'failed', $this->error),
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
        ];
    }
}
