<?php

namespace App\Http\Resources;

use App\Security\CredentialCipher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderCredentialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'label' => $this->label,
            'masked_key' => $this->maskedKey(app(CredentialCipher::class)),
            'default_model' => $this->default_model,
            'is_default' => $this->is_default,
            'last_verified_at' => $this->last_verified_at,
            'last_used_at' => $this->last_used_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'metadata' => $this->when($this->metadata !== null, $this->metadata),
        ];
    }
}
