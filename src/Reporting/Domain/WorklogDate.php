<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Domain;

final readonly class WorklogDate
{
    private function __construct(private \DateTimeImmutable $value) {}

    public static function fromString(string $value, ReportTimeZone $timezone): self
    {
        $date = $timezone->parseDate($value);
        if ($date === null || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Invalid worklog date.');
        }

        return new self($date);
    }

    public function day(): int
    {
        return (int) $this->value->format('j');
    }

    public function toString(): string
    {
        return $this->value->format('Y-m-d');
    }
}
