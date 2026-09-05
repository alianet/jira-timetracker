<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Application\Port;

use App\Reporting\Application\ExportedFile;
use App\Reporting\Application\WorklogReportData;
use App\Reporting\Domain\ReportPeriod;

interface ReportExporter
{
    public function export(WorklogReportData $report, ReportPeriod $period): ExportedFile;
}
