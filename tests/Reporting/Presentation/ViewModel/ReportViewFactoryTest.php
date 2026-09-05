<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Reporting\Presentation\ViewModel;

use App\Reporting\Domain\IssueKey;
use App\Reporting\Domain\MonthlyReport;
use App\Reporting\Domain\ReportPeriod;
use App\Reporting\Domain\ReportTimeZone;
use App\Reporting\Domain\WorkdayPolicy;
use App\Reporting\Domain\WorklogDate;
use App\Reporting\Domain\WorklogDuration;
use App\Reporting\Domain\WorklogEntry;
use App\Reporting\Presentation\ViewModel\ReportViewContext;
use App\Reporting\Presentation\ViewModel\ReportViewFactory;
use App\Shared\Infrastructure\FixedClock;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\TestCase;

final class ReportViewFactoryTest extends TestCase
{
    public function testMapsDomainReportAndPageContextToTypedPresentationValues(): void
    {
        $calendar = new class implements \App\Reporting\Domain\Port\HolidayCalendar {
            public function holidaysOn(\DateTimeImmutable $date): array
            {
                return $date->format('Y-m-d') === '2024-01-01' ? ['new_year'] : [];
            }

            public function isDayOff(\DateTimeImmutable $date): bool
            {
                return $date->format('Y-m-d') === '2024-01-01';
            }
        };
        $timezone = ReportTimeZone::fromName('Europe/Warsaw');
        $report = MonthlyReport::generate([
            new WorklogEntry('12', WorklogDate::fromString('2024-01-02', $timezone), IssueKey::fromString('APP-2'), 'App', 'Second issue', WorklogDuration::fromSeconds(5400), 'Done'),
            new WorklogEntry('11', WorklogDate::fromString('2024-01-02', $timezone), IssueKey::fromString('APP-2'), 'App', 'Second issue', WorklogDuration::fromSeconds(1800), ''),
        ], new ReportPeriod(2024, 1, $timezone), 28800, new WorkdayPolicy(
            28800,
            $calendar,
            new FixedClock(new \DateTimeImmutable('2024-01-03', new \DateTimeZone('Europe/Warsaw'))),
            $timezone,
        ));
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn(string $id): string => "translated:{$id}");
        $view = new ReportViewFactory($translator, static fn(string $issue): string => "https://jira.test/browse/{$issue}")
            ->create(
                $report,
                new ReportViewContext(
                    'account-1',
                    'Jan Kowalski',
                    'avatar.png',
                    true,
                    '2024-01-02',
                    'Jira',
                    'https://jira.test',
                    'csrf-token',
                    true,
                    true,
                    2024,
                    2,
                    [2023, 2024],
                    ['meeting'],
                ),
            );

        self::assertSame('https://jira.test/browse/APP-2', $view->rows[0]->url);
        self::assertSame(2.0, $view->rows[0]->totalHours);
        self::assertSame([
            ['id' => '12', 'timeSpent' => '1h 30m', 'comment' => 'Done'],
            ['id' => '11', 'timeSpent' => '30m', 'comment' => ''],
        ], $view->rows[0]->dailyWorklogs[2]);
        self::assertSame('day-holiday', $view->days[1]->cssClasses);
        self::assertSame('translated:calendar.holiday.new_year', $view->days[1]->title);
        self::assertSame('day-alert', $view->days[2]->cssClasses);
        self::assertSame(2.0, $view->days[2]->totalHours);
        self::assertSame('csrf-token', $view->csrfToken);
        self::assertTrue($view->exportEnabled);
        self::assertSame(2023, $view->previousYear);
        self::assertSame(12, $view->previousMonth);
        self::assertSame(2024, $view->nextYear);
        self::assertSame(2, $view->nextMonth);
        self::assertFalse($view->isCurrentPeriod);
        self::assertSame(['meeting'], $view->worklogTags);
    }
}
