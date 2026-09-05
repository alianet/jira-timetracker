<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Reporting\Infrastructure\Calendar;

use App\Kernel\Exception\ApplicationConfigException;
use App\Reporting\Infrastructure\Calendar\HolidayCalendarFactory;
use PHPUnit\Framework\TestCase;

final class HolidayCalendarFactoryTest extends TestCase
{
    public function testCreatesNationalCalendar(): void
    {
        $calendar = new HolidayCalendarFactory()->create(HolidayCalendarFactory::COUNTRY_POLAND);

        self::assertNotSame([], $calendar->holidaysOn(new \DateTimeImmutable('2026-05-01')));
        self::assertTrue($calendar->isDayOff(new \DateTimeImmutable('2026-05-01')));
        self::assertSame([], $calendar->holidaysOn(new \DateTimeImmutable('2026-05-04')));
        self::assertFalse($calendar->isDayOff(new \DateTimeImmutable('2026-05-04')));
    }

    public function testCreatesRegionalCalendar(): void
    {
        $calendar = new HolidayCalendarFactory()->create(
            HolidayCalendarFactory::COUNTRY_GERMANY,
            HolidayCalendarFactory::VERSION_GERMANY_BAVARIA,
        );

        self::assertNotSame([], $calendar->holidaysOn(new \DateTimeImmutable('2026-08-15')));
    }

    public function testRejectsUnknownCalendarVersion(): void
    {
        $this->expectException(ApplicationConfigException::class);

        new HolidayCalendarFactory()->create(HolidayCalendarFactory::COUNTRY_POLAND, 'Mazovia');
    }
}
