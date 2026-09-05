<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Application\Authentication;

/**
 * Everything Identity knows about a request-scoped connection: the credentials,
 * where to send them and in which mode they were obtained. Identity deliberately stops here —
 * turning a connection into Reporting or TimeTracking adapters is the composition root's job.
 */
final readonly class Connection
{
    private function __construct(
        public AuthenticationMode $mode,
        public bool $authenticated,
        /** Human-facing site address, e.g. https://example.atlassian.net (no trailing slash). */
        public string $siteUrl,
        /** Base address for REST calls; differs from the site address for OAuth. Empty when unauthenticated. */
        public string $apiBaseUrl,
        /** Atlassian cloud identifier; empty outside OAuth. */
        public string $cloudId,
        /** Bearer token (OAuth) or personal API token. Empty when unauthenticated. */
        public string $token,
        /** Basic-auth login paired with a personal token; empty for OAuth. */
        public string $login,
    ) {}

    public static function oauth(string $accessToken, string $cloudId, string $siteUrl, string $apiBaseUrl): self
    {
        return new self(
            AuthenticationMode::InteractiveOAuth,
            true,
            rtrim($siteUrl, '/'),
            rtrim($apiBaseUrl, '/'),
            $cloudId,
            $accessToken,
            '',
        );
    }

    public static function personalToken(string $siteUrl, string $login, string $apiToken): self
    {
        $siteUrl = rtrim($siteUrl, '/');

        return new self(AuthenticationMode::PersonalToken, true, $siteUrl, $siteUrl, '', $apiToken, $login);
    }

    /** Connection metadata without credentials: the caller still has to authenticate. */
    public static function unauthenticated(AuthenticationMode $mode, string $siteUrl): self
    {
        return new self($mode, false, rtrim($siteUrl, '/'), '', '', '', '');
    }

    public function isInteractive(): bool
    {
        return $this->mode === AuthenticationMode::InteractiveOAuth;
    }
}
