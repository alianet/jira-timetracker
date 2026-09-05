<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Presentation\ViewModel;

use App\Reporting\Domain\MonthlyReport;
use App\Reporting\Domain\ReportDay;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ReportViewFactory
{
    /** @param \Closure(string): string $issueUrl */
    public function __construct(
        private TranslatorInterface $translator,
        private \Closure $issueUrl,
    ) {}

    public function create(MonthlyReport $report, ReportViewContext $context): ReportView
    {
        $year = $report->period->year;
        $month = $report->period->month;
        $previousMonth = $month === 1 ? 12 : $month - 1;
        $previousYear = $month === 1 ? $year - 1 : $year;
        $nextMonth = $month === 12 ? 1 : $month + 1;
        $nextYear = $month === 12 ? $year + 1 : $year;

        $rows = [];
        foreach ($report->rows as $row) {
            $dailyWorklogs = [];
            foreach ($row->dailyWorklogs as $day => $worklogs) {
                $dailyWorklogs[$day] = array_map(static fn($entry): array => [
                    'id' => $entry->id,
                    'timeSpent' => self::formatSeconds($entry->duration->toSeconds()),
                    'comment' => $entry->comment,
                ], $worklogs);
            }
            $rows[] = new ReportRowView(
                issue: $row->issue->toString(),
                summary: $row->summary,
                url: ($this->issueUrl)($row->issue->toString()),
                dailyWorklogs: $dailyWorklogs,
                dailyHours: array_map(self::hours(...), $row->dailySeconds),
                totalHours: self::hours($row->totalSeconds),
            );
        }

        $days = [];
        foreach ($report->days as $number => $day) {
            $days[$number] = new ReportDayView(
                number: $number,
                date: $day->date,
                weekday: $day->weekday,
                cssClasses: $this->dayClasses($day),
                title: $this->dayTitle($day),
                totalHours: self::hours($day->totalSeconds),
            );
        }

        return new ReportView(
            year: $year,
            month: $month,
            previousYear: $previousYear,
            previousMonth: $previousMonth,
            nextYear: $nextYear,
            nextMonth: $nextMonth,
            currentYear: $context->currentYear,
            currentMonth: $context->currentMonth,
            isCurrentPeriod: $year === $context->currentYear && $month === $context->currentMonth,
            startDate: $report->period->startDate(),
            endDate: $report->period->endDate(),
            defaultWorklogDate: $context->defaultWorklogDate,
            totalHours: round(self::hours($report->totalSeconds), 2),
            totalDays: round($report->totalDays, 2),
            days: $days,
            rows: $rows,
            accountId: $context->accountId,
            displayName: $context->displayName !== '' ? $context->displayName : $context->accountId,
            avatarUrl: $context->avatarUrl,
            isOwnReport: $context->isOwnReport,
            siteName: $context->siteName,
            siteUrl: $context->siteUrl,
            csrfToken: $context->csrfToken,
            exportEnabled: $context->exportEnabled,
            saved: $context->saved,
            years: $context->years,
            worklogTags: $context->worklogTags,
        );
    }

    private static function hours(int $seconds): float
    {
        return $seconds / 3600;
    }

    private static function formatSeconds(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return trim(($hours > 0 ? "{$hours}h" : '') . ($minutes > 0 ? " {$minutes}m" : '')) ?: '0m';
    }

    private function dayClasses(ReportDay $day): string
    {
        return implode(' ', array_filter([
            $day->weekend ? 'day-weekend' : null,
            $day->holidays !== [] ? 'day-holiday' : null,
            $day->alert ? 'day-alert' : null,
            $day->today ? 'day-today' : null,
        ]));
    }

    private function dayTitle(ReportDay $day): string
    {
        if ($day->holidays !== []) {
            return implode(', ', array_map(
                fn(string $holiday): string => $this->translator->trans('calendar.holiday.' . $holiday),
                $day->holidays,
            ));
        }

        if ($day->weekend) {
            return $this->translator->trans('calendar.day.weekend');
        }

        return $day->alert ? $this->translator->trans('calendar.day.alert') : '';
    }
}
