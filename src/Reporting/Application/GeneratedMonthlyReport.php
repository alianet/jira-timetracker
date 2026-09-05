<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Application;

use App\Reporting\Domain\MonthlyReport;

final readonly class GeneratedMonthlyReport
{
    public function __construct(
        public MonthlyReport $report,
        public string $accountId,
        public string $displayName,
        public string $avatarUrl,
        public bool $isOwnReport,
    ) {}
}
