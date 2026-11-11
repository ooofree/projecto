<?php
declare(strict_types=1);

namespace ProjectO\Security;

use RuntimeException;

/**
 * Deterministic, keyed encryption for single sensitive field values such as an
 * identity document number.
 *
 * Ciphertext layout: "v1:" + base64( 12-byte nonce | 16-byte GCM tag | ciphertext ).
 * The same value encrypts to different ciphertext every write (fresh nonce), so a
 * leaked row cannot be compared directly; equality lookups therefore use
 * fingerprint(), which is a keyed HMAC rather than a plain hash.
 *
 * The key comes from the APP_DATA_KEY environment variable (64 hex characters =
 * 32 bytes). It is never stored in the database and never written to a log.
 */
final class FieldCipher
{
    private const PREFIX = 'v1:';
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_BYTES = 12;
    private const TAG_BYTES = 16;

    private function __construct(private readonly string $key)
    {
    }

    /**
     * Returns null when the deployment has not configured a data key, so callers
     * can keep working for every field that is not encrypted and explain the gap.
     */
    public static function fromEnvironment(): ?self
    {
        $configured = getenv('APP_DATA_KEY');
        if (!is_string($configured) || trim($configured) === '') {
            return null;
        }

        return self::fromKeyString(trim($configured));
    }

    public static function fromKeyString(string $key): ?self
    {
        if (preg_match('/^[0-9a-fA-F]{64}$/', $key) !== 1) {
            throw new RuntimeException('APP_DATA_KEY must be 64 hexadecimal characters (32 bytes).');
        }

        $decoded = hex2bin($key);
        if ($decoded === false || strlen($decoded) !== 32) {
            throw new RuntimeException('APP_DATA_KEY could not be decoded to a 32-byte key.');
        }

        return new self($decoded);
    }

    public static function generateKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_BYTES
        );
        if ($ciphertext === false || strlen($tag) !== self::TAG_BYTES) {
            throw new RuntimeException('Unable to encrypt the field value.');
        }

        return self::PREFIX . base64_encode($nonce . $tag . $ciphertext);
    }

    /**
     * Returns null when the stored value was not written by this format or cannot
     * be authenticated, which callers surface as "unavailable" instead of a guess.
     */
    public function decrypt(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }
        if (!str_starts_with($payload, self::PREFIX)) {
            return null;
        }

        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        $minimum = self::NONCE_BYTES + self::TAG_BYTES + 1;
        if ($raw === false || strlen($raw) < $minimum) {
            return null;
        }

        $nonce = substr($raw, 0, self::NONCE_BYTES);
        $tag = substr($raw, self::NONCE_BYTES, self::TAG_BYTES);
        $ciphertext = substr($raw, self::NONCE_BYTES + self::TAG_BYTES);
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        return $plaintext === false ? null : $plaintext;
    }

    /** Keyed digest used for equality checks and duplicate detection. */
    public function fingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, $this->key);
    }
}
