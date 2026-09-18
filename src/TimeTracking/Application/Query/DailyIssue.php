<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Application\Query;

use App\TimeTracking\Domain\Model\IssueKey;

final readonly class DailyIssue
{
    public function __construct(
        public IssueKey $key,
        public string $summary,
        public string $description,
        public string $timeSpent,
        public string $url,
        public string $status = '',
        /** @var list<int> */
        public array $sprintIds = [],
        public bool $unassigned = false,
    ) {}
}
