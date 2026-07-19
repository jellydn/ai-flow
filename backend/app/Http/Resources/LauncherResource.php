<?php

namespace App\Http\Resources;

use App\Services\LauncherMetaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LauncherResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Receives a raw array (never an Eloquent model) because the controller
     * merges built-in Launcher rows with UserLauncher rows into a single
     * collection, adding the `is_custom` discriminator along the way.
     */
    public function toArray(Request $request): array
    {
        /** @var array{slug: string, name: string, description: string, input_type: string, is_custom: bool} $row */
        $row = $this->resource;

        $slug = $row['slug'];
        $isCustom = $row['is_custom'];

        $metaService = app(LauncherMetaService::class);
        $meta = $isCustom
            ? $metaService->forCustom($slug)
            : $metaService->forBuiltIn($slug);

        return [
            'id' => $slug,
            'slug' => $slug,
            'name' => $row['name'],
            'description' => $row['description'],
            'input_type' => $row['input_type'],
            'icon' => $meta['icon'],
            'tone' => $meta['tone'],
            'is_custom' => $isCustom,
        ];
    }
}
