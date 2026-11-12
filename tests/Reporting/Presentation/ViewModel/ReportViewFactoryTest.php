<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Reporting\Presentation\ViewModel;

use App\Kernel\Presentation\Twig\EnvironmentFactory;
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
                return match ($date->format('Y-m-d')) {
                    '2024-01-01' => ['new_year'],
                    '2024-01-02', '2024-01-04' => ['observance'],
                    '2024-01-07' => ['weekend_holiday'],
                    default => [],
                };
            }

            public function isDayOff(\DateTimeImmutable $date): bool
            {
                return in_array($date->format('Y-m-d'), ['2024-01-01', '2024-01-07'], true);
            }
        };
        $timezone = ReportTimeZone::fromName('Europe/Warsaw');
        $report = MonthlyReport::generate([
            new WorklogEntry('12', WorklogDate::fromString('2024-01-02', $timezone), IssueKey::fromString('APP-2'), 'App', 'Second issue', WorklogDuration::fromSeconds(24299), 'Done'),
            new WorklogEntry('11', WorklogDate::fromString('2024-01-02', $timezone), IssueKey::fromString('APP-2'), 'App', 'Second issue', WorklogDuration::fromSeconds(1800), ''),
        ], new ReportPeriod(2024, 1, $timezone), 28800, new WorkdayPolicy(
            28800,
            $calendar,
            new FixedClock(new \DateTimeImmutable('2024-01-03', new \DateTimeZone('Europe/Warsaw'))),
            $timezone,
        ));
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn(string $id): string => "translated:{$id}");
        $issueUrl = static fn(string $issue): string => "https://jira.test/browse/{$issue}";
        $context = new ReportViewContext(
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
            true,
            2024,
            2,
            [2023, 2024],
            ['meeting'],
        );
        $view = new ReportViewFactory($translator, $issueUrl)->create($report, $context);

        self::assertSame('https://jira.test/browse/APP-2', $view->rows[0]->url);
        self::assertSame(26099 / 3600, $view->rows[0]->totalHours);
        self::assertSame('7h14m', $view->rows[0]->totalTime);
        self::assertSame('7h14m', $view->rows[0]->dailyTimes[2]);
        self::assertSame([
            ['id' => '12', 'timeSpent' => '6h 44m', 'comment' => 'Done'],
            ['id' => '11', 'timeSpent' => '30m', 'comment' => ''],
        ], $view->rows[0]->dailyWorklogs[2]);
        self::assertSame('day-holiday day-holiday-off', $view->days[1]->cssClasses);
        self::assertSame('translated:calendar.holiday.new_year — translated:calendar.day.holiday_day_off', $view->days[1]->title);
        self::assertTrue($view->days[1]->holiday);
        self::assertTrue($view->days[1]->dayOff);
        self::assertSame('day-holiday day-holiday-working day-alert', $view->days[2]->cssClasses);
        self::assertSame('translated:calendar.holiday.observance — translated:calendar.day.holiday_working — translated:calendar.day.alert', $view->days[2]->title);
        self::assertFalse($view->days[2]->dayOff);
        self::assertSame('day-holiday day-holiday-working', $view->days[4]->cssClasses);
        self::assertSame('day-weekend', $view->days[6]->cssClasses);
        self::assertFalse($view->days[6]->holiday);
        self::assertSame('day-weekend day-holiday day-holiday-off', $view->days[7]->cssClasses);
        self::assertSame('translated:calendar.holiday.weekend_holiday — translated:calendar.day.holiday_day_off — translated:calendar.day.weekend', $view->days[7]->title);
        self::assertSame(26099 / 3600, $view->days[2]->totalHours);
        self::assertSame('7h14m', $view->days[2]->totalTime);
        self::assertSame('7h14m', $view->totalTime);
        self::assertSame('csrf-token', $view->csrfToken);
        self::assertTrue($view->exportEnabled);
        self::assertTrue($view->descriptionRequired);
        self::assertSame(2023, $view->previousYear);
        self::assertSame(12, $view->previousMonth);
        self::assertSame(2024, $view->nextYear);
        self::assertSame(2, $view->nextMonth);
        self::assertFalse($view->isCurrentPeriod);
        self::assertSame(['meeting'], $view->worklogTags);

        $twig = new EnvironmentFactory()->create(dirname(__DIR__, 4) . '/templates', $translator);
        $html = $twig->render('report/_matrix.html.twig', ['report' => $view]);
        self::assertStringContainsString('class="calendar-legend"', $html);
        self::assertStringContainsString('1<span class="holiday-marker" aria-hidden="true">◆</span>', $html);
        self::assertStringContainsString('2<span class="holiday-marker" aria-hidden="true">◇</span>', $html);
        self::assertStringContainsString('7<span class="holiday-marker" aria-hidden="true">◆</span>', $html);

        $decimalView = new ReportViewFactory(
            $translator,
            $issueUrl,
            ReportViewFactory::TIME_FORMAT_DECIMAL,
        )->create($report, $context);

        self::assertSame('7,25', $decimalView->rows[0]->dailyTimes[2]);
        self::assertSame('7,25', $decimalView->rows[0]->totalTime);
        self::assertSame('7,25', $decimalView->days[2]->totalTime);
        self::assertSame('7,25', $decimalView->totalTime);
    }
}
