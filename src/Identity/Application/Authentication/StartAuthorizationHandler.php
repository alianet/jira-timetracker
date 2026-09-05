<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Application\Authentication;

final readonly class StartAuthorizationHandler
{
    public function __construct(private AuthorizationGateway $gateway) {}

    public function handle(string $state): ?string
    {
        return $this->gateway->isInteractive() ? $this->gateway->authorizationUrl($state) : null;
    }
}
