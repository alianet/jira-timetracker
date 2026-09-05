<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Application\Authentication;

final readonly class AuthenticatedAccess
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public int $expiresAt,
        public string $cloudId,
        public string $siteUrl,
        public string $siteName,
    ) {}
}
