<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Reporting\Domain;

use App\Reporting\Domain\Port\HolidayCalendar;
use App\Reporting\Domain\ReportTimeZone;
use App\Reporting\Domain\WorkdayPolicy;
use App\Shared\Infrastructure\FixedClock;
use PHPUnit\Framework\TestCase;

final class WorkdayPolicyTest extends TestCase
{
    public function testAlertsOnlyForIncompletePastOrCurrentWorkingDayInConfiguredTimezone(): void
    {
        $calendar = new class implements HolidayCalendar {
            public function holidaysOn(\DateTimeImmutable $date): array
            {
                return in_array($date->format('Y-m-d'), ['2026-05-01', '2026-05-04'], true) ? ['holiday'] : [];
            }

            public function isDayOff(\DateTimeImmutable $date): bool
            {
                return $date->format('Y-m-d') === '2026-05-01';
            }
        };
        $policy = new WorkdayPolicy(
            28800,
            $calendar,
            new FixedClock(new \DateTimeImmutable('2026-05-05 21:30:00', new \DateTimeZone('UTC'))),
            ReportTimeZone::fromName('Pacific/Auckland'),
        );

        self::assertFalse($policy->describe(new \DateTimeImmutable('2026-05-01'), 0)->alert);
        self::assertFalse($policy->describe(new \DateTimeImmutable('2026-05-02'), 0)->alert);
        self::assertTrue($policy->describe(new \DateTimeImmutable('2026-05-04'), 0)->alert);
        self::assertTrue($policy->describe(new \DateTimeImmutable('2026-05-05'), 1)->alert);
        self::assertTrue($policy->describe(new \DateTimeImmutable('2026-05-06'), 0)->alert);
        self::assertFalse($policy->describe(new \DateTimeImmutable('2026-05-07'), 0)->alert);
    }
}
