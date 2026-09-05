<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use App\Shared\Domain\Clock;

final readonly class FixedClock implements Clock
{
    public function __construct(private \DateTimeImmutable $dateTime) {}

    public function now(): \DateTimeImmutable
    {
        return $this->dateTime;
    }
}
