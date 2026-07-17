<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProviderCredentialRequest;
use App\Http\Requests\UpdateProviderCredentialRequest;
use App\Http\Resources\ProviderCredentialResource;
use App\Models\ProviderCredential;
use App\Security\CredentialCipher;
use App\Support\AiProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProviderCredentialController extends Controller
{
    public function __construct(
        private CredentialCipher $cipher,
        private AiProviderRegistry $registry,
    ) {}

    /**
     * List the authenticated user's saved provider credentials.
     */
    public function index(): AnonymousResourceCollection
    {
        $credentials = ProviderCredential::query()
            ->where('user_id', request()->user()->id)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get();

        return ProviderCredentialResource::collection($credentials);
    }

    /**
     * Save a new encrypted provider credential.
     */
    public function store(StoreProviderCredentialRequest $request): JsonResponse
    {
        $credential = new ProviderCredential;
        $credential->user_id = $request->user()->id;
        $credential->provider = $request->validated('provider');
        $credential->label = $request->validated('label');
        $credential->encrypted_api_key = $this->cipher->encrypt($request->validated('api_key'));

        if ($request->has('base_url')) {
            $credential->encrypted_base_url = $this->cipher->encrypt($request->validated('base_url'));
        }
        if ($request->has('default_model')) {
            $credential->default_model = $request->validated('default_model');
        }
        if ($request->boolean('is_default')) {
            $credential->is_default = true;
        }
        $credential->save();

        return (new ProviderCredentialResource($credential))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a credential's label, model, default status, or key.
     */
    public function update(UpdateProviderCredentialRequest $request, ProviderCredential $credential): ProviderCredentialResource
    {
        $this->authorize('manage', $credential);

        if ($request->has('label')) {
            $credential->label = $request->validated('label');
        }
        if ($request->has('default_model')) {
            $credential->default_model = $request->validated('default_model');
        }
        if ($request->has('metadata')) {
            $credential->metadata = $request->validated('metadata');
        }
        if ($request->has('is_default')) {
            $credential->is_default = $request->boolean('is_default');
        }
        if ($request->filled('api_key')) {
            $credential->encrypted_api_key = $this->cipher->encrypt($request->validated('api_key'));
        }
        if ($request->has('base_url') && $request->validated('base_url') !== null) {
            $credential->encrypted_base_url = $this->cipher->encrypt($request->validated('base_url'));
        }

        $credential->save();

        return new ProviderCredentialResource($credential);
    }

    /**
     * Delete a provider credential.
     */
    public function destroy(ProviderCredential $credential): JsonResponse
    {
        $this->authorize('manage', $credential);

        $credential->delete();

        return response()->json(['message' => 'Credential deleted.'], 200);
    }

    /**
     * Verify a credential's API key against the provider.
     */
    public function verify(ProviderCredential $credential): JsonResponse
    {
        $this->authorize('manage', $credential);

        $apiKey = $credential->decryptApiKey($this->cipher);

        $provider = $this->registry->get($credential->provider, $apiKey);

        $result = $provider->verifyCredential($apiKey);

        if ($result['valid']) {
            $credential->update(['last_verified_at' => now()]);
        }

        return response()->json($result);
    }

    /**
     * Set a credential as the default for its provider.
     */
    public function makeDefault(ProviderCredential $credential): ProviderCredentialResource
    {
        $this->authorize('manage', $credential);

        $credential->update(['is_default' => true]);

        return new ProviderCredentialResource($credential);
    }
}
