<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Reporting\Domain;

use App\Reporting\Domain\IssueKey;
use App\Reporting\Domain\MonthlyReport;
use App\Reporting\Domain\Port\HolidayCalendar;
use App\Reporting\Domain\ReportPeriod;
use App\Reporting\Domain\ReportTimeZone;
use App\Reporting\Domain\WorkdayPolicy;
use App\Reporting\Domain\WorklogDate;
use App\Reporting\Domain\WorklogDuration;
use App\Reporting\Domain\WorklogEntry;
use App\Shared\Infrastructure\FixedClock;
use PHPUnit\Framework\TestCase;

final class MonthlyReportTest extends TestCase
{
    public function testGroupsNaturallyAndKeepsExactSecondAggregates(): void
    {
        $timezone = ReportTimeZone::fromName('Pacific/Auckland');
        $policy = new WorkdayPolicy(21600, $this->emptyCalendar(), new FixedClock(new \DateTimeImmutable('2026-04-02')), $timezone);
        $report = MonthlyReport::generate([
            $this->entry('1', '2026-04-01', 'APP-10', 3601),
            $this->entry('2', '2026-04-01', 'APP-2', 7201),
            $this->entry('3', '2026-04-01', 'APP-10', 5401),
        ], new ReportPeriod(2026, 4, $timezone), 21600, $policy);

        self::assertSame(['APP-2', 'APP-10'], array_map(static fn($row): string => $row->issue->toString(), $report->rows));
        self::assertSame(9002, $report->rows[1]->dailySeconds[1]);
        self::assertSame(16203, $report->dailySeconds[1]);
        self::assertSame(16203, $report->totalSeconds);
        self::assertSame(16203 / 21600, $report->totalDays);
        self::assertCount(30, $report->days);
    }

    private function entry(string $id, string $date, string $issue, int $seconds): WorklogEntry
    {
        return new WorklogEntry(
            $id,
            WorklogDate::fromString($date, ReportTimeZone::fromName('Pacific/Auckland')),
            IssueKey::fromString($issue),
            'Project',
            "Summary {$issue}",
            WorklogDuration::fromSeconds($seconds),
            'comment',
        );
    }

    private function emptyCalendar(): HolidayCalendar
    {
        return new class implements HolidayCalendar {
            public function holidaysOn(\DateTimeImmutable $date): array
            {
                return [];
            }

            public function isDayOff(\DateTimeImmutable $date): bool
            {
                return false;
            }
        };
    }
}
