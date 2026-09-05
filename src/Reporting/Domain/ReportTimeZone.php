<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Domain;

use Safe\DateTimeImmutable;

final readonly class ReportTimeZone
{
    private function __construct(private \DateTimeZone $value) {}

    public static function fromName(string $name): self
    {
        try {
            return new self(new \DateTimeZone($name));
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException('Invalid report time zone.', 0, $exception);
        }
    }

    public function parseDate(string $date): ?\DateTimeImmutable
    {
        try {
            return DateTimeImmutable::createFromFormat('!Y-m-d', $date, $this->value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function date(string $date): \DateTimeImmutable
    {
        return new DateTimeImmutable($date, $this->value);
    }

    public function midnight(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTimezone($this->value)->setTime(0, 0);
    }

    public function name(): string
    {
        return $this->value->getName();
    }
}
