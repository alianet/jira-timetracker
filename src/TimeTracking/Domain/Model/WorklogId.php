<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

use App\TimeTracking\Domain\Exception\InvalidWorklog;

final readonly class WorklogId
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        $value = trim($value);
        if ($value === '' || !ctype_digit($value)) {
            throw new InvalidWorklog('Nieprawidłowy identyfikator wpisu czasu.');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
