<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Application\Command;

final readonly class AddWorklog
{
    public function __construct(
        public string $issue,
        public string $date,
        public string $timeSpent,
        public string $comment,
    ) {}
}
