<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Infrastructure\Session;

use App\Kernel\Support\ApiValue;
use Safe\Exceptions\SodiumException as SafeSodiumException;

use function Safe\sodium_crypto_aead_xchacha20poly1305_ietf_decrypt;
use function Safe\sodium_crypto_aead_xchacha20poly1305_ietf_encrypt;

final readonly class SessionTokenCipher
{
    private const string PREFIX = 'v1.';
    private const string ADDITIONAL_DATA = 'jira-timetracker/oauth-session/v1';

    private string $key;

    public function __construct(string $encodedKey)
    {
        try {
            $key = sodium_base642bin(trim($encodedKey), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (\SodiumException $exception) {
            throw new \InvalidArgumentException('SESSION_ENCRYPTION_KEY musi być kluczem base64url.', previous: $exception);
        }

        if (strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new \InvalidArgumentException('SESSION_ENCRYPTION_KEY musi kodować dokładnie 32 bajty.');
        }

        $this->key = $key;
    }

    /** @param array<string, mixed> $value */
    public function encrypt(array $value): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $plaintext = json_encode($value, JSON_THROW_ON_ERROR);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            self::ADDITIONAL_DATA,
            $nonce,
            $this->key,
        );

        return self::PREFIX . sodium_bin2base64(
            $nonce . $ciphertext,
            SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
        );
    }

    /** @return array<string, mixed>|null */
    public function decrypt(string $value): ?array
    {
        if (!str_starts_with($value, self::PREFIX)) {
            return null;
        }

        try {
            $payload = sodium_base642bin(
                substr($value, strlen(self::PREFIX)),
                SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
            );
        } catch (\SodiumException) {
            return null;
        }

        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (strlen($payload) < $nonceLength + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
            return null;
        }

        try {
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                substr($payload, $nonceLength),
                self::ADDITIONAL_DATA,
                substr($payload, 0, $nonceLength),
                $this->key,
            );
        } catch (SafeSodiumException) {
            return null;
        }

        try {
            $decoded = json_decode($plaintext, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? ApiValue::object($decoded) : null;
    }
}
