<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Presentation\ViewModel;

final readonly class ReportRowView
{
    /**
     * @param array<int, list<array{id: string, timeSpent: string, comment: string}>> $dailyWorklogs
     * @param array<int, float> $dailyHours
     */
    public function __construct(
        public string $issue,
        public string $summary,
        public string $url,
        public array $dailyWorklogs,
        public array $dailyHours,
        public float $totalHours,
    ) {}
}
