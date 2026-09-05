<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

use App\TimeTracking\Domain\Exception\InvalidWorklog;

use function Safe\preg_match;

final readonly class IssueKey
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        $value = strtoupper(trim($value));
        if (preg_match('/^[A-Z][A-Z0-9_]*-\d+$/', $value) !== 1) {
            throw new InvalidWorklog('Nieprawidłowy numer zadania Jiry.');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
