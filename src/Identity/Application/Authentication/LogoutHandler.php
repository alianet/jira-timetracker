<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Application\Authentication;

final readonly class LogoutHandler
{
    public function __construct(private AccessTokenStore $store) {}

    public function handle(): void
    {
        $this->store->clear();
    }
}
