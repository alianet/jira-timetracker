<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Domain;

final readonly class MonthlyReport
{
    /**
     * @param list<WorklogEntry> $entries
     * @param list<ReportRow> $rows
     * @param array<int, int> $dailySeconds
     * @param array<int, ReportDay> $days
     */
    private function __construct(
        public ReportPeriod $period,
        public array $entries,
        public array $rows,
        public array $dailySeconds,
        public array $days,
        public int $totalSeconds,
        public float $totalDays,
    ) {}

    /** @param list<WorklogEntry> $entries */
    public static function generate(array $entries, ReportPeriod $period, int $dailySecondsLimit, WorkdayPolicy $policy): self
    {
        $dayNumbers = range(1, $period->numberOfDays());
        $dailySeconds = array_fill_keys($dayNumbers, 0);
        $rows = new ReportRows();

        foreach ($entries as $entry) {
            $rows->add($entry, $dayNumbers);
            $dailySeconds[$entry->date->day()] += $entry->duration->toSeconds();
        }

        $totalSeconds = array_sum($dailySeconds);

        return new self(
            $period,
            $entries,
            $rows->sorted(),
            $dailySeconds,
            self::describeDays($dayNumbers, $period, $dailySeconds, $policy),
            $totalSeconds,
            $totalSeconds / $dailySecondsLimit,
        );
    }

    /**
     * @param list<int> $dayNumbers
     * @param array<int, int> $dailySeconds
     * @return array<int, ReportDay>
     */
    private static function describeDays(array $dayNumbers, ReportPeriod $period, array $dailySeconds, WorkdayPolicy $policy): array
    {
        $days = [];
        foreach ($dayNumbers as $day) {
            $days[$day] = $policy->describe($period->dateForDay($day), $dailySeconds[$day]);
        }

        return $days;
    }
}
