<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

use App\TimeTracking\Domain\Exception\InvalidWorklog;

use function Safe\preg_match;
use function Safe\preg_match_all;

final readonly class TimeAmount
{
    private const int SECONDS_PER_MINUTE = 60;
    private const int SECONDS_PER_HOUR = 3600;

    private function __construct(private int $seconds) {}

    public static function fromString(string $value, WorkTimeUnits $units): self
    {
        $value = trim($value);
        if (preg_match('/^(\d+)(?:[,.](\d+))?\s*h$/i', $value, $matches) === 1) {
            if (!isset($matches[1])) {
                throw new \LogicException('Brak dopasowania liczby godzin.');
            }
            $hours = (int) $matches[1];
            $fraction = $matches[2] ?? '';
            $minutes = $fraction === '' ? 0 : (int) round(((int) $fraction / (10 ** strlen($fraction))) * 60);

            return new self(($hours * self::SECONDS_PER_HOUR) + ($minutes * self::SECONDS_PER_MINUTE));
        }

        if (preg_match('/^(?:\d+\s*[wdhm]\s*)+$/i', $value) !== 1) {
            throw new InvalidWorklog('Podaj czas w formacie Jiry, np. 1h 30m.');
        }

        preg_match_all('/(\d+)\s*([wdhm])/i', $value, $parts, PREG_SET_ORDER);
        $seconds = 0;
        foreach ($parts as $part) {
            $seconds += (int) $part[1] * match (strtolower($part[2])) {
                'w' => $units->secondsPerWeek,
                'd' => $units->secondsPerDay,
                'h' => self::SECONDS_PER_HOUR,
                'm' => self::SECONDS_PER_MINUTE,
                default => throw new InvalidWorklog('Podaj czas w formacie Jiry, np. 1h 30m.'),
            };
        }

        return new self($seconds);
    }

    public function inSeconds(): int
    {
        return $this->seconds;
    }

}
