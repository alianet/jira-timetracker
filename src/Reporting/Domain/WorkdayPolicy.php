<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Domain;

use App\Reporting\Domain\Port\HolidayCalendar;
use App\Shared\Domain\Clock;

final readonly class WorkdayPolicy
{
    public function __construct(
        private int $requiredSeconds,
        private HolidayCalendar $holidayCalendar,
        private Clock $clock,
        private ReportTimeZone $timezone,
    ) {
        if ($requiredSeconds <= 0) {
            throw new \InvalidArgumentException('Required work time must be positive.');
        }
    }

    public function describe(\DateTimeImmutable $date, int $workedSeconds): ReportDay
    {
        $date = $this->timezone->midnight($date);
        $today = $this->timezone->midnight($this->clock->now());
        $weekend = (int) $date->format('N') >= 6;
        $holidays = $this->holidayCalendar->holidaysOn($date);
        $dayOff = $this->holidayCalendar->isDayOff($date);

        return new ReportDay(
            date: $date->format('Y-m-d'),
            weekday: (int) $date->format('N'),
            weekend: $weekend,
            holidays: $holidays,
            alert: !$weekend && !$dayOff && $date <= $today && $workedSeconds < $this->requiredSeconds,
            today: $date == $today,
            totalSeconds: $workedSeconds,
        );
    }
}
