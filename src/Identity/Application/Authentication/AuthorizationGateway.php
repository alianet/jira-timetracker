<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Application\Authentication;

interface AuthorizationGateway
{
    public function isInteractive(): bool;

    public function authorizationUrl(string $state): string;

    public function exchange(string $code): AuthenticatedAccess;
}
