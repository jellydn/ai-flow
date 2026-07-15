<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bootstrap super admin (database seed)
    |--------------------------------------------------------------------------
    |
    | When SUPER_ADMIN_BOOTSTRAP_EMAIL is set, migrate --seed creates or promotes
    | that user as super admin. A one-time password is emailed only when the
    | account is newly created (requires RESEND_API_KEY or another mail driver).
    |
    */

    'bootstrap_email' => env('SUPER_ADMIN_BOOTSTRAP_EMAIL'),

    'bootstrap_name' => env('SUPER_ADMIN_BOOTSTRAP_NAME', 'Super Admin'),

];
