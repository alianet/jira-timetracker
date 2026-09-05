<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Application\Query;

final readonly class SearchIssuesHandler
{
    private const int RESULT_LIMIT = 10;

    public function __construct(private IssueDirectory $directory) {}

    /** @return list<IssueSearchResult> */
    public function handle(string $phrase): array
    {
        $phrase = IssueSearchPhrase::fromString($phrase);

        return $phrase->isSearchable() ? $this->directory->search($phrase, self::RESULT_LIMIT) : [];
    }
}
