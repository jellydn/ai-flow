<?php

namespace Database\Factories;

use App\Models\ProviderCredential;
use App\Models\User;
use App\Security\CredentialCipher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderCredential>
 */
class ProviderCredentialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The encrypted_api_key is encrypted via CredentialCipher so factory-
     * created credentials are realistic (and decryptable in test env where
     * APP_KEY is set by phpunit.xml).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cipher = app(CredentialCipher::class);

        return [
            'user_id' => User::factory(),
            'provider' => $this->faker->randomElement(['openai', 'anthropic', 'gemini', 'openrouter']),
            'label' => $this->faker->words(2, true),
            'encrypted_api_key' => $cipher->encrypt('sk-test-'.$this->faker->uuid()),
            'default_model' => null,
            'is_default' => false,
        ];
    }

    /**
     * Credential for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Credential for a specific provider with a known plaintext key.
     */
    public function forProvider(string $provider, string $plainKey = 'sk-test-key'): static
    {
        $cipher = app(CredentialCipher::class);

        return $this->state(fn (array $attributes) => [
            'provider' => $provider,
            'encrypted_api_key' => $cipher->encrypt($plainKey),
        ]);
    }

    /**
     * Mark this credential as the user's default.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
