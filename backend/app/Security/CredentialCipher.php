<?php

namespace App\Security;

use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class CredentialCipher
{
    /**
     * Encrypt a plaintext API key for database storage.
     *
     * Uses Laravel's Crypt facade (authenticated AES-256 encryption via APP_KEY).
     */
    public function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            throw new RuntimeException('Cannot encrypt an empty credential.');
        }

        return Crypt::encryptString($plaintext);
    }

    /**
     * Decrypt a ciphertext back to the original plaintext API key.
     *
     * Callers must not store, log, serialize, or return the plaintext value.
     */
    public function decrypt(string $ciphertext): string
    {
        return Crypt::decryptString($ciphertext);
    }

    /**
     * Return a masked representation suitable for display.
     *
     * Example: "sk-abcd...9X2A"
     *
     * The mask reveals the prefix and the last 4 characters, with the middle
     * replaced by an ellipsis. Keys shorter than 8 characters are fully masked.
     */
    public function mask(string $plaintext): string
    {
        $length = mb_strlen($plaintext);

        if ($length <= 8) {
            return str_repeat('*', max($length, 4));
        }

        $prefix = mb_substr($plaintext, 0, 4);
        $suffix = mb_substr($plaintext, -4);

        return "{$prefix}...{$suffix}";
    }
}
