<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Application\Authentication;

interface ConnectionProvider
{
    /** Resolves credentials for the current request, refreshing them when the mode supports it. */
    public function connection(): Connection;
}
