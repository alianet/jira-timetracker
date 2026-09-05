<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Config;

use App\Kernel\Exception\ApplicationConfigException;

final readonly class WorkdayConfig
{
    private const int SECONDS_PER_HOUR = 3600;

    public function __construct(
        public int $reportingDaySeconds,
        public string $timezone,
    ) {}

    public static function fromConfig(Config $config): self
    {
        $rawValue = str_replace(',', '.', trim($config->required('DAILY_HOURS_LIMIT')));

        if (!is_numeric($rawValue) || ($dailyHoursLimit = (float) $rawValue) <= 0) {
            throw ApplicationConfigException::invalidValue('DAILY_HOURS_LIMIT', 'liczba większa od zera');
        }

        $timezone = trim($config->nullable('APP_TIMEZONE') ?? 'Europe/Warsaw');
        try {
            new \DateTimeZone($timezone);
        } catch (\Throwable $exception) {
            throw ApplicationConfigException::invalidValue('APP_TIMEZONE', 'prawidłowa nazwa strefy czasowej', $exception);
        }

        return new self((int) round($dailyHoursLimit * self::SECONDS_PER_HOUR), $timezone);
    }
}
