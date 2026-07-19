<?php

namespace App\Http\Resources;

use App\Contracts\LauncherMetaInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LauncherResource extends JsonResource
{
    private static ?LauncherMetaInterface $metaService = null;

    public static function setLauncherMetaService(LauncherMetaInterface $service): void
    {
        self::$metaService = $service;
    }

    public function toArray(Request $request): array
    {
        $slug = $this->resource['slug'] ?? $this->slug;
        $isCustom = $this->resource['is_custom'] ?? false;

        $service = self::$metaService ?? app(LauncherMetaInterface::class);
        $meta = $isCustom
            ? $service->forCustom($slug)
            : $service->forBuiltIn($slug);

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
