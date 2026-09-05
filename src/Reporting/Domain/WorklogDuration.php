<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Domain;

final readonly class WorklogDuration
{
    private function __construct(private int $seconds) {}

    public static function fromSeconds(int $seconds): self
    {
        if ($seconds < 0) {
            throw new \InvalidArgumentException('Worklog duration cannot be negative.');
        }

        return new self($seconds);
    }

    public function toSeconds(): int
    {
        return $this->seconds;
    }
}
