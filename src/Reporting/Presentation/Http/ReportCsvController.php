<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Presentation\Http;

use App\Kernel\Exception\ApplicationRuntimeException as WorkLogRuntimeException;
use App\Kernel\Presentation\Http\Request;
use App\Kernel\Presentation\Http\Response;
use App\Kernel\Support\ApiValue;
use App\Reporting\Application\ExportMonthlyReportHandler;
use App\Reporting\Domain\ReportPeriod;
use App\Reporting\Domain\ReportTimeZone;
use App\Reporting\Domain\WorklogAuthorId;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class ReportCsvController
{
    /** @param callable(array<array-key, mixed>&): ExportMonthlyReportHandler $exportReport */
    public function __construct(
        private mixed $exportReport,
        private bool $enabled,
        private string $disabledMessage,
        private ReportTimeZone $timezone,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function export(Request $request): Response
    {
        if (!$this->enabled) {
            $this->logger->warning('CSV report export rejected because the feature is disabled.');
            throw WorkLogRuntimeException::create($this->disabledMessage);
        }
        $query = ApiValue::stringMap($request->query);
        $reportQuery = ReportQuery::fromQuery($query);
        $authorId = trim($query['accountId'] ?? '') === '' ? null : WorklogAuthorId::fromString($query['accountId']);
        $file = ($this->exportReport)($request->session)->handle(
            new ReportPeriod($reportQuery->year, $reportQuery->month, $this->timezone),
            $authorId,
        );
        $this->logger->info('Monthly Jira report exported to CSV.', [
            'year' => $reportQuery->year,
            'month' => $reportQuery->month,
            'selected_author' => $authorId !== null,
            'content_bytes' => strlen($file->content),
        ]);

        return new Response($file->content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $file->filename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
