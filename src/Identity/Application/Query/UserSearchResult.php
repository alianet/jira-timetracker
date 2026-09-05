<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Application\Query;

final readonly class UserSearchResult
{
    public function __construct(
        public AccountId $accountId,
        public string $displayName,
        public string $avatarUrl,
    ) {}
}
