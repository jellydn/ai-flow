<x-mail::message>
# Sign in to {{ config('app.name') ?: 'AI Flow' }}

Click the button below to sign in. This link expires in {{ $expiresIn }} and can only be used once.

<x-mail::button :url="$url">
Sign in
</x-mail::button>

If the button doesn't work, copy and paste this URL into your browser:

{{ $url }}

<x-mail::subcopy>
If you didn't request this link, you can safely ignore this email.
</x-mail::subcopy>
</x-mail::message>
