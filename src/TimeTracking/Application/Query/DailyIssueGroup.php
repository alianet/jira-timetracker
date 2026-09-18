<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Application\Query;

final readonly class DailyIssueGroup
{
    /** @param list<DailyIssue> $issues */
    public function __construct(
        public ?DailySprint $sprint,
        public array $issues,
    ) {}
}
