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
    */

    'encryption_key' => env('CREDENTIAL_ENCRYPTION_KEY', env('APP_KEY')),

];
