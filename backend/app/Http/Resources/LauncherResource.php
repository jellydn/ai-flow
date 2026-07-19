<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LauncherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $slug = $this->resource['slug'] ?? $this->slug;
        $meta = $this->resource['is_custom'] ?? false
            ? customLauncherMeta($slug)
            : launcherServerMeta($slug);

        return [
            'id' => $slug,
            'slug' => $slug,
            'name' => $this->resource['name'] ?? $this->name,
            'description' => $this->resource['description'] ?? $this->description,
            'input_type' => $this->resource['input_type'] ?? $this->input_type,
            'icon' => $meta['icon'],
            'tone' => $meta['tone'],
            'is_custom' => $this->resource['is_custom'] ?? false,
        ];
    }
}
