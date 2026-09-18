<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Application\Query;

final readonly class DailyOverview
{
    /**
     * @param list<DailyIssue> $lastReportedIssues
     * @param list<DailyIssue> $assignedIssues
     * @param list<string> $statuses
     * @param list<DailyIssueGroup> $assignedIssueGroups
     */
    public function __construct(
        public ?string $lastReportedDate,
        public array $lastReportedIssues,
        public array $assignedIssues,
        public array $statuses,
        public array $assignedIssueGroups = [],
    ) {}
}
