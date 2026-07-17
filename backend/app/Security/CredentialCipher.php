<?php

namespace App\Security;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use RuntimeException;

class CredentialCipher
{
    private Encrypter $encrypter;

    public function __construct()
    {
        $key = (string) config('credentials.encryption_key');
        $cipher = (string) config('app.cipher', 'AES-256-CBC');

        // An explicitly-empty CREDENTIAL_ENCRYPTION_KEY= in .env would
        // suppress the fallback and produce a zero-length key. Guard
        // against that by treating empty string the same as unset.
        if ($key === '') {
            $key = (string) config('app.key');
        }

        // Parse the key: strip the "base64:" prefix if present (same format as APP_KEY).
        if (Str::startsWith($key, 'base64:')) {
            $key = base64_decode(Str::after($key, 'base64:'));
        }

        $this->encrypter = new Encrypter($key, $cipher);
    }

    /**
     * Encrypt a plaintext API key for database storage.
     *
     * Uses a dedicated encryption key (CREDENTIAL_ENCRYPTION_KEY), falling
     * back to APP_KEY, with authenticated AES-256 encryption.
     */
    public function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            throw new RuntimeException('Cannot encrypt an empty credential.');
        }

        return $this->encrypter->encryptString($plaintext);
    }

    /**
     * Decrypt a ciphertext back to the original plaintext API key.
     *
     * Callers must not store, log, serialize, or return the plaintext value.
     */
    public function decrypt(string $ciphertext): string
    {
        return $this->encrypter->decryptString($ciphertext);
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
