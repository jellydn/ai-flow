<?php

namespace App\Http\Resources;

use App\Services\LauncherMetaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LauncherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $slug = $this->resource['slug'] ?? $this->slug;
        $isCustom = $this->resource['is_custom'] ?? false;

        $metaService = app(LauncherMetaService::class);
        $meta = $isCustom
            ? $metaService->forCustom($slug)
            : $metaService->forBuiltIn($slug);

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
