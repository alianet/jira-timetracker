<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Application;

use App\Reporting\Application\Port\WorklogReportSource;
use App\Reporting\Domain\MonthlyReport;
use App\Reporting\Domain\ReportPeriod;
use App\Reporting\Domain\WorkdayPolicy;
use App\Reporting\Domain\WorklogAuthorId;

final readonly class GenerateMonthlyReportHandler
{
    public function __construct(
        private WorklogReportSource $source,
        private WorkdayPolicy $workdayPolicy,
        private int $dailySecondsLimit,
    ) {}

    public function handle(ReportPeriod $period, ?WorklogAuthorId $authorId = null): GeneratedMonthlyReport
    {
        $data = $this->source->forUser($period, $authorId);
        $report = MonthlyReport::generate($data->entries, $period, $this->dailySecondsLimit, $this->workdayPolicy);

        return new GeneratedMonthlyReport(
            $report,
            $data->accountId,
            $data->displayName,
            $data->avatarUrl,
            $data->isOwnReport,
        );
    }
}
