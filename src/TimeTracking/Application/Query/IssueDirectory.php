<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Application\Query;

interface IssueDirectory
{
    /** @return list<IssueSearchResult> */
    public function search(IssueSearchPhrase $phrase, int $limit): array;
}
