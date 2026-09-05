<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

final readonly class WorkTimeUnits
{
    public function __construct(
        public int $secondsPerDay,
        public int $secondsPerWeek,
    ) {
        if ($secondsPerDay <= 0 || $secondsPerWeek <= 0) {
            throw new \InvalidArgumentException('Work time units must be positive.');
        }
    }
}
