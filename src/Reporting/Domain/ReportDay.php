<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Domain;

final readonly class ReportDay
{
    /** @param list<string> $holidays */
    public function __construct(
        public string $date,
        public int $weekday,
        public bool $weekend,
        public array $holidays,
        public bool $alert,
        public bool $today,
        public int $totalSeconds,
    ) {}
}
