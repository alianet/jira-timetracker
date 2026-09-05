<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

final readonly class WorklogComment
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        return new self(trim($value));
    }

    public function toString(): string
    {
        return $this->value;
    }
}
