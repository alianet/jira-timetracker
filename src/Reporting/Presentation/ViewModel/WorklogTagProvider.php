<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Presentation\ViewModel;

final class WorklogTagProvider
{
    public function __construct(private readonly string $tags = '') {}

    /** @return list<string> */
    public function all(): array
    {
        return array_values(array_unique(array_filter(
            array_map(trim(...), explode(',', $this->tags)),
            static fn(string $tag): bool => $tag !== '',
        )));
    }
}
