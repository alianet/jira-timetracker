<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Application\Authentication;

enum AccountType: string
{
    case Individual = 'individual';
    case Company = 'company';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(
            static fn(self $accountType): string => $accountType->value,
            self::cases(),
        );
    }
}
