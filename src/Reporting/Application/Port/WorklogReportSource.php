<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Application\Port;

use App\Reporting\Application\WorklogReportData;
use App\Reporting\Domain\ReportPeriod;
use App\Reporting\Domain\WorklogAuthorId;

interface WorklogReportSource
{
    public function forUser(ReportPeriod $period, ?WorklogAuthorId $authorId = null): WorklogReportData;
}
