<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Infrastructure\Session;

use App\Identity\Application\Authentication\AccessTokenStore;
use App\Identity\Application\Authentication\AuthenticatedAccess;
use App\Kernel\Support\ApiValue;

final class SessionAccessTokenStore implements AccessTokenStore
{
    /** @param array<array-key, mixed> $session */
    public function __construct(
        private array &$session,
        private readonly ?SessionTokenCipher $cipher,
    ) {}

    public function load(): ?AuthenticatedAccess
    {
        if ($this->cipher === null) {
            unset($this->session['atlassian']);

            return null;
        }

        $encrypted = ApiValue::stringValue($this->session['atlassian'] ?? null);
        $value = $this->cipher->decrypt($encrypted);
        $token = ApiValue::stringValue($value['access_token'] ?? null);
        $cloudId = ApiValue::stringValue($value['cloud_id'] ?? null);
        if ($token === '' || $cloudId === '') {
            unset($this->session['atlassian']);

            return null;
        }

        return new AuthenticatedAccess(
            $token,
            ($refresh = ApiValue::stringValue($value['refresh_token'] ?? null)) !== '' ? $refresh : null,
            ApiValue::intValue($value['expires_at'] ?? null),
            $cloudId,
            ApiValue::stringValue($value['site_url'] ?? null),
            ApiValue::stringValue($value['site_name'] ?? null),
        );
    }

    public function save(AuthenticatedAccess $access): void
    {
        if ($this->cipher === null) {
            throw new \LogicException('Magazyn tokenów OAuth jest wyłączony w trybie individual.');
        }

        $this->session['atlassian'] = $this->cipher->encrypt([
            'access_token' => $access->accessToken,
            'refresh_token' => $access->refreshToken,
            'expires_at' => $access->expiresAt,
            'cloud_id' => $access->cloudId,
            'site_url' => $access->siteUrl,
            'site_name' => $access->siteName,
        ]);
    }

    public function clear(): void
    {
        unset($this->session['atlassian']);
    }
}
