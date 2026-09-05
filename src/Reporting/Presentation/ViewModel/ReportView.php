<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Presentation\ViewModel;

final readonly class ReportView
{
    /**
     * @param list<int> $years
     * @param list<string> $worklogTags
     * @param array<int, ReportDayView> $days
     * @param list<ReportRowView> $rows
     */
    public function __construct(
        public int $year,
        public int $month,
        public int $previousYear,
        public int $previousMonth,
        public int $nextYear,
        public int $nextMonth,
        public int $currentYear,
        public int $currentMonth,
        public bool $isCurrentPeriod,
        public string $startDate,
        public string $endDate,
        public string $defaultWorklogDate,
        public float $totalHours,
        public float $totalDays,
        public array $days,
        public array $rows,
        public string $accountId,
        public string $displayName,
        public string $avatarUrl,
        public bool $isOwnReport,
        public string $siteName,
        public string $siteUrl,
        public string $csrfToken,
        public bool $exportEnabled,
        public bool $saved,
        public array $years,
        public array $worklogTags,
    ) {}
}
