<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credential Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used exclusively for encrypting and decrypting stored
    | AI provider API keys (BYOK). Using a dedicated key isolates credential
    | encryption from APP_KEY, so rotating APP_KEY does not invalidate all
    | stored credentials.
    |
    | Falls back to APP_KEY when not set, preserving backward compatibility
    | with credentials encrypted under the previous scheme.
    |
    | IMPORTANT: Losing this key makes all stored credentials unrecoverable.
    | Back up this key securely before rotating it, and plan a re-encryption
    | process if you need to change it for existing credentials.
    |
    | Rotation procedure:
    |   1. Decrypt all existing ProviderCredential rows with the OLD key.
    |   2. Set the NEW CREDENTIAL_ENCRYPTION_KEY in the environment.
    |   3. Re-encrypt every decrypted key and update the rows.
    |   4. Verify a sample credential decrypts and verifies against its provider.
    |   5. Destroy the OLD key only after confirming all credentials work.
    |
    | In production, AppServiceProvider emits a warning log when this key is
    | unset (i.e. the APP_KEY fallback is in effect), so operators are alerted
    | that rotating APP_KEY would invalidate stored credentials.
    |
    */

    'encryption_key' => env('CREDENTIAL_ENCRYPTION_KEY', env('APP_KEY')),

];
