<?php

namespace Tests\Unit;

use App\Security\CredentialCipher;
use RuntimeException;
use Tests\TestCase;

class CredentialCipherTest extends TestCase
{
    private CredentialCipher $cipher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cipher = new CredentialCipher;
    }

    public function test_encrypt_and_decrypt_round_trip(): void
    {
        config()->set('credentials.encryption_key', 'base64:'.base64_encode(random_bytes(32)));
        $cipher = new CredentialCipher;

        $plaintext = 'sk-test-key-12345';
        $encrypted = $cipher->encrypt($plaintext);

        $this->assertNotSame($plaintext, $encrypted);
        $this->assertGreaterThan(0, strlen($encrypted), 'Encrypted output should not be empty.');
        $this->assertSame($plaintext, $cipher->decrypt($encrypted));
    }

    public function test_empty_plaintext_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot encrypt an empty credential.');

        $this->cipher->encrypt('');
    }

    public function test_mask_short_key(): void
    {
        $masked = $this->cipher->mask('abcd');

        $this->assertSame('****', $masked);
    }

    public function test_mask_normal_key(): void
    {
        $masked = $this->cipher->mask('sk-abcdefghijklmnop');

        $this->assertSame('sk-a...mnop', $masked);
    }

    public function test_mask_eight_char_key_is_fully_masked(): void
    {
        $masked = $this->cipher->mask('12345678');

        $this->assertSame('********', $masked);
    }
}
