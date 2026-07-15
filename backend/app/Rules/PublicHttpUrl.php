<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Reject URLs that could be used for SSRF (localhost, private/reserved IPs, metadata endpoints).
 */
class PublicHttpUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $parts = parse_url($value);
        if (! is_array($parts)) {
            $fail('The :attribute must be a valid public HTTP or HTTPS URL.');

            return;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('The :attribute must use HTTP or HTTPS.');

            return;
        }

        $host = strtolower($parts['host'] ?? '');
        if ($host === '') {
            $fail('The :attribute must include a host.');

            return;
        }

        $hostForIp = trim($host, '[]');

        if ($this->isBlockedHost($host) || $this->isBlockedHost($hostForIp)) {
            $fail('The :attribute must not point to localhost or private networks.');

            return;
        }

        if (filter_var($hostForIp, FILTER_VALIDATE_IP)) {
            if (! $this->isPublicIp($hostForIp)) {
                $fail('The :attribute must not use a private or reserved IP address.');

                return;
            }

            return;
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if ($records === false || $records === []) {
            return;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip) && ! $this->isPublicIp($ip)) {
                $fail('The :attribute resolves to a private or reserved network.');

                return;
            }
        }
    }

    private function isBlockedHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return true;
        }

        return $host === 'metadata.google.internal';
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
