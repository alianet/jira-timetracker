<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Application\Query;

final readonly class SearchUsersHandler
{
    private const int RESULT_LIMIT = 10;

    public function __construct(private UserDirectory $directory) {}

    /** @return list<UserSearchResult> */
    public function handle(string $phrase): array
    {
        $phrase = UserSearchPhrase::fromString($phrase);

        return $phrase->isSearchable() ? $this->directory->search($phrase, self::RESULT_LIMIT) : [];
    }
}
