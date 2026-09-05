<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Domain;

final readonly class ReportPeriod
{
    public function __construct(
        public int $year,
        public int $month,
        private ReportTimeZone $timezone,
    ) {
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Invalid report period.');
        }
    }

    public function startDate(): string
    {
        return sprintf('%04d-%02d-01', $this->year, $this->month);
    }

    public function endDate(): string
    {
        return $this->timezone->date($this->startDate())->format('Y-m-t');
    }

    public function numberOfDays(): int
    {
        return (int) $this->timezone->date($this->startDate())->format('t');
    }

    public function dateForDay(int $day): \DateTimeImmutable
    {
        return $this->timezone->date(sprintf('%04d-%02d-%02d', $this->year, $this->month, $day));
    }
}
