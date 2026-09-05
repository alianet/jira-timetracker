<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Application\Authentication;

interface AccessTokenStore
{
    public function load(): ?AuthenticatedAccess;

    public function save(AuthenticatedAccess $access): void;

    public function clear(): void;
}
