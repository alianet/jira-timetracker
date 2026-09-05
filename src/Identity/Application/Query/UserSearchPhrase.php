<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Application\Query;

final readonly class UserSearchPhrase
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        return new self(trim($value));
    }

    public function isSearchable(): bool
    {
        return mb_strlen($this->value) >= 2;
    }
}
