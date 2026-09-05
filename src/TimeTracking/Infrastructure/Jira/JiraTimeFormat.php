<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Infrastructure\Jira;

use App\TimeTracking\Domain\Model\WorkTimeUnits;

final class JiraTimeFormat
{
    public const int SECONDS_PER_MINUTE = 60;
    public const int SECONDS_PER_HOUR = 3600;
    public const int SECONDS_PER_DAY = 8 * self::SECONDS_PER_HOUR;
    public const int DAYS_PER_WEEK = 5;
    public const int SECONDS_PER_WEEK = self::DAYS_PER_WEEK * self::SECONDS_PER_DAY;

    public static function units(): WorkTimeUnits
    {
        return new WorkTimeUnits(self::SECONDS_PER_DAY, self::SECONDS_PER_WEEK);
    }

    public static function formatSeconds(int $seconds): string
    {
        $parts = [];
        foreach ([
            'w' => self::SECONDS_PER_WEEK,
            'd' => self::SECONDS_PER_DAY,
            'h' => self::SECONDS_PER_HOUR,
            'm' => self::SECONDS_PER_MINUTE,
        ] as $unit => $unitSeconds) {
            $amount = intdiv($seconds, $unitSeconds);
            if ($amount > 0) {
                $parts[] = $amount . $unit;
                $seconds %= $unitSeconds;
            }
        }

        return $parts === [] ? '0m' : implode(' ', $parts);
    }
}
