<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Domain;

final class ReportRowAccumulator
{
    /** @var array<int, int> */
    private array $dailySeconds;

    /** @var array<int, list<WorklogEntry>> */
    private array $dailyWorklogs;

    private int $totalSeconds = 0;

    /** @param list<int> $dayNumbers */
    public function __construct(private readonly WorklogEntry $firstEntry, array $dayNumbers)
    {
        $this->dailySeconds = array_fill_keys($dayNumbers, 0);
        $this->dailyWorklogs = array_fill_keys($dayNumbers, []);
    }

    public function belongsTo(IssueKey $issue): bool
    {
        return $this->firstEntry->issue->equals($issue);
    }

    public function add(WorklogEntry $entry): void
    {
        $day = $entry->date->day();
        $seconds = $entry->duration->toSeconds();
        $this->dailySeconds[$day] += $seconds;
        $this->dailyWorklogs[$day][] = $entry;
        $this->totalSeconds += $seconds;
    }

    public function issue(): IssueKey
    {
        return $this->firstEntry->issue;
    }

    public function toReportRow(): ReportRow
    {
        return new ReportRow(
            $this->firstEntry->issue,
            $this->firstEntry->project,
            $this->firstEntry->summary,
            $this->dailySeconds,
            $this->dailyWorklogs,
            $this->totalSeconds,
        );
    }
}
