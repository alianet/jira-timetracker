<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Domain;

final readonly class ReportRow
{
    /**
     * @param array<int, int> $dailySeconds
     * @param array<int, list<WorklogEntry>> $dailyWorklogs
     */
    public function __construct(
        public IssueKey $issue,
        public string $project,
        public string $summary,
        public array $dailySeconds,
        public array $dailyWorklogs,
        public int $totalSeconds,
    ) {}
}
