<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Presentation\Http;

use Safe\DateTimeImmutable;
use Safe\Exceptions\DatetimeException;

final readonly class ReportQuery
{
    private function __construct(
        public int $year,
        public int $month,
        public DateTimeImmutable $now,
    ) {}

    /**
     * @param array<string, string> $query
     * @throws DatetimeException
     */
    public static function fromQuery(array $query): self
    {
        $now = new DateTimeImmutable();
        $year = filter_var($query['year'] ?? $now->format('Y'), FILTER_VALIDATE_INT) ?: (int) $now->format('Y');
        $month = filter_var($query['month'] ?? $now->format('n'), FILTER_VALIDATE_INT) ?: (int) $now->format('n');

        return new self(
            min(2100, max(2000, $year)),
            min(12, max(1, $month)),
            $now,
        );
    }

    public function isCurrent(): bool
    {
        return $this->year === (int) $this->now->format('Y')
            && $this->month === (int) $this->now->format('n');
    }
}
