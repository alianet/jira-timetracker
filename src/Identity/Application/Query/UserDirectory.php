<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Application\Query;

interface UserDirectory
{
    /** @return list<UserSearchResult> */
    public function search(UserSearchPhrase $phrase, int $limit): array;
}
