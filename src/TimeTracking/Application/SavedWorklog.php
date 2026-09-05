<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Application;

final readonly class SavedWorklog
{
    public function __construct(
        public int $year,
        public int $month,
        public string $issue,
    ) {}
}
