<x-mail::message>
# Super admin account created

Your **{{ config('app.name') }}** super admin account is ready.

**Email:** {{ $email }}

**Password:** {{ $password }}

Sign in to the control panel:

<x-mail::button :url="$adminUrl">
Open admin panel
</x-mail::button>

Use this password for both the admin panel (`/admin`) and the main app sign-in if you use password authentication.

Change your password after first sign-in if your account settings allow it.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
