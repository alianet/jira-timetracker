<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use App\Shared\Domain\Clock;
use Safe\DateTimeImmutable;

final readonly class SystemClock implements Clock
{
    public function __construct(private \DateTimeZone $timezone) {}

    public function now(): \DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
}
