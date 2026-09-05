<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Domain;

use function Safe\preg_match;

final readonly class WorklogAuthorId
{
    private const int MAX_LENGTH = 128;

    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > self::MAX_LENGTH || preg_match('/\A[A-Za-z0-9:_-]+\z/D', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid worklog author identifier.');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
