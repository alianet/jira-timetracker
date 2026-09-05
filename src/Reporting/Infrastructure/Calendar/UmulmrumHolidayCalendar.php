<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Infrastructure\Calendar;

use App\Reporting\Domain\Port\HolidayCalendar;
use Umulmrum\Holiday\Constant\HolidayType;
use Umulmrum\Holiday\HolidayCalculatorInterface;
use Umulmrum\Holiday\Model\HolidayList;
use Umulmrum\Holiday\Provider\HolidayProviderInterface;

/** @internal Created by HolidayCalendarFactory. */
final class UmulmrumHolidayCalendar implements HolidayCalendar
{
    /** @var array<int, HolidayList> */
    private array $holidaysByYear = [];

    /** @param class-string<HolidayProviderInterface> $provider */
    public function __construct(
        private readonly HolidayCalculatorInterface $calculator,
        private readonly string $provider,
    ) {}

    public function holidaysOn(\DateTimeImmutable $date): array
    {
        $result = [];
        foreach ($this->holidaysForYear((int) $date->format('Y')) as $holiday) {
            if ($holiday->getSimpleDate() === $date->format('Y-m-d')) {
                $result[] = $holiday->getName();
            }
        }

        return $result;
    }

    public function isDayOff(\DateTimeImmutable $date): bool
    {
        foreach ($this->holidaysForYear((int) $date->format('Y')) as $holiday) {
            if ($holiday->getSimpleDate() === $date->format('Y-m-d') && $holiday->hasType(HolidayType::DAY_OFF)) {
                return true;
            }
        }

        return false;
    }

    private function holidaysForYear(int $year): HolidayList
    {
        return $this->holidaysByYear[$year] ??= $this->calculator->calculate($this->provider, $year);
    }
}
