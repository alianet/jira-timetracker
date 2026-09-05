<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Presentation\ViewModel;

final class WorklogTagProvider
{
    /** @var list<string> */
    private const array TAGS = [
        'Analiza',
        'Analiza testów',
        'CR',
        'Daily',
        'Development',
        'Development po CR',
        'Przygotowanie raportów',
        'Spotkanie organizacyjne',
        'Refinement',
        'Szkolenie',
        'Testy',
        'Wsparcie testów',
    ];

    /** @return list<string> */
    public function all(): array
    {
        return self::TAGS;
    }
}
