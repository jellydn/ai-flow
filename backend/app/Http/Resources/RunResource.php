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

        // Priority: userLauncher before launcher.
        // Custom-launcher runs store a placeholder built-in launcher_id (NOT NULL FK),
        // so launcher->slug would return the wrong value. userLauncher takes precedence.
        // DO NOT swap these — that would be a regression for custom-launcher runs.
        $launcherSlug = $this->userLauncher?->slug ?? $this->launcher?->slug;

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
