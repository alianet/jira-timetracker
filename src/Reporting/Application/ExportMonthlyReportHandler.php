<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Application;

use App\Reporting\Application\Port\ReportExporter;
use App\Reporting\Application\Port\WorklogReportSource;
use App\Reporting\Domain\ReportPeriod;
use App\Reporting\Domain\WorklogAuthorId;

final readonly class ExportMonthlyReportHandler
{
    public function __construct(
        private WorklogReportSource $source,
        private ReportExporter $exporter,
    ) {}

    public function handle(ReportPeriod $period, ?WorklogAuthorId $authorId = null): ExportedFile
    {
        return $this->exporter->export($this->source->forUser($period, $authorId), $period);
    }
}
