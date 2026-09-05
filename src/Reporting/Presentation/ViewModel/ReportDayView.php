<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Presentation\ViewModel;

final readonly class ReportDayView
{
    public function __construct(
        public int $number,
        public string $date,
        public int $weekday,
        public string $cssClasses,
        public string $title,
        public float $totalHours,
    ) {}
}
