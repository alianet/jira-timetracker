<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Kernel\Config;

use App\Kernel\Config\WorkdayConfig;
use App\Kernel\Exception\ApplicationConfigException;
use Tests\TestCase;

final class WorkdayConfigTest extends TestCase
{
    public function testRequiresDailyHoursLimit(): void
    {
        $this->expectException(ApplicationConfigException::class);

        WorkdayConfig::fromConfig($this->createConfig([]));
    }

    public function testReadsDailyHoursLimit(): void
    {
        $config = WorkdayConfig::fromConfig(
            $this->createConfig([
                'DAILY_HOURS_LIMIT' => '6.5',
                'APP_TIMEZONE' => 'Pacific/Auckland',
            ])
        );

        self::assertSame(23400, $config->reportingDaySeconds);
        self::assertSame('Pacific/Auckland', $config->timezone);
    }

    public function testRejectsNonPositiveDailyHoursLimit(): void
    {
        $this->expectException(ApplicationConfigException::class);

        WorkdayConfig::fromConfig($this->createConfig(['DAILY_HOURS_LIMIT' => '0']));
    }
}
