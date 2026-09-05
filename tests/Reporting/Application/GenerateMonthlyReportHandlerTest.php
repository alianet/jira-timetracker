<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Reporting\Application;

use App\Reporting\Application\GenerateMonthlyReportHandler;
use App\Reporting\Application\Port\WorklogReportSource;
use App\Reporting\Application\WorklogReportData;
use App\Reporting\Domain\IssueKey;
use App\Reporting\Domain\Port\HolidayCalendar;
use App\Reporting\Domain\ReportPeriod;
use App\Reporting\Domain\ReportTimeZone;
use App\Reporting\Domain\WorkdayPolicy;
use App\Reporting\Domain\WorklogAuthorId;
use App\Reporting\Domain\WorklogDate;
use App\Reporting\Domain\WorklogDuration;
use App\Reporting\Domain\WorklogEntry;
use App\Shared\Infrastructure\FixedClock;
use PHPUnit\Framework\TestCase;

final class GenerateMonthlyReportHandlerTest extends TestCase
{
    public function testLoadsEntriesThroughPortAndGeneratesReport(): void
    {
        $source = new class implements WorklogReportSource {
            public ?ReportPeriod $receivedPeriod = null;
            public ?WorklogAuthorId $receivedAuthorId = null;

            public function forUser(ReportPeriod $period, ?WorklogAuthorId $authorId = null): WorklogReportData
            {
                $this->receivedPeriod = $period;
                $this->receivedAuthorId = $authorId;

                return new WorklogReportData(
                    [new WorklogEntry(
                        '1',
                        WorklogDate::fromString('2026-04-03', ReportTimeZone::fromName('Europe/Warsaw')),
                        IssueKey::fromString('APP-1'),
                        'Project',
                        'Summary',
                        WorklogDuration::fromSeconds(3600),
                        '',
                    )],
                    'selected',
                    'Selected User',
                    'avatar',
                    false,
                );
            }
        };
        $calendar = new class implements HolidayCalendar {
            public function holidaysOn(\DateTimeImmutable $date): array
            {
                return [];
            }

            public function isDayOff(\DateTimeImmutable $date): bool
            {
                return false;
            }
        };
        $handler = new GenerateMonthlyReportHandler(
            $source,
            new WorkdayPolicy(28800, $calendar, new FixedClock(new \DateTimeImmutable('2026-04-03')), ReportTimeZone::fromName('Europe/Warsaw')),
            28800,
        );

        $result = $handler->handle(
            new ReportPeriod(2026, 4, ReportTimeZone::fromName('Europe/Warsaw')),
            WorklogAuthorId::fromString(' selected '),
        );

        self::assertSame(3600, $result->report->totalSeconds);
        self::assertSame('Selected User', $result->displayName);
        self::assertSame('selected', $source->receivedAuthorId?->toString());
        self::assertSame(4, $source->receivedPeriod?->month);
    }
}
