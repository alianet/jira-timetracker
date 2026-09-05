<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Reporting\Application;

use App\Reporting\Application\ExportedFile;
use App\Reporting\Application\ExportMonthlyReportHandler;
use App\Reporting\Application\Port\ReportExporter;
use App\Reporting\Application\Port\WorklogReportSource;
use App\Reporting\Application\WorklogReportData;
use App\Reporting\Domain\IssueKey;
use App\Reporting\Domain\ReportPeriod;
use App\Reporting\Domain\ReportTimeZone;
use App\Reporting\Domain\WorklogAuthorId;
use App\Reporting\Domain\WorklogDate;
use App\Reporting\Domain\WorklogDuration;
use App\Reporting\Domain\WorklogEntry;
use PHPUnit\Framework\TestCase;

final class ExportMonthlyReportHandlerTest extends TestCase
{
    public function testLoadsReportFromSourceAndPassesItToExporter(): void
    {
        $data = new WorklogReportData(
            [new WorklogEntry(
                '1',
                WorklogDate::fromString('2026-04-03', self::timezone()),
                IssueKey::fromString('APP-1'),
                'Project',
                'Summary',
                WorklogDuration::fromSeconds(3600),
                '',
            )],
            'selected',
            'Selected User',
            '',
            false,
        );
        $source = new class ($data) implements WorklogReportSource {
            public ?ReportPeriod $period = null;
            public ?WorklogAuthorId $authorId = null;

            public function __construct(private WorklogReportData $data) {}

            public function forUser(ReportPeriod $period, ?WorklogAuthorId $authorId = null): WorklogReportData
            {
                $this->period = $period;
                $this->authorId = $authorId;

                return $this->data;
            }
        };
        $exporter = new class implements ReportExporter {
            public ?WorklogReportData $report = null;
            public ?ReportPeriod $period = null;

            public function export(WorklogReportData $report, ReportPeriod $period): ExportedFile
            {
                $this->report = $report;
                $this->period = $period;

                return new ExportedFile('content', 'report.csv');
            }
        };
        $period = new ReportPeriod(2026, 4, self::timezone());

        $file = new ExportMonthlyReportHandler($source, $exporter)->handle($period, WorklogAuthorId::fromString('account-7'));

        self::assertSame('report.csv', $file->filename);
        self::assertSame($data, $exporter->report);
        self::assertSame($period, $exporter->period);
        self::assertSame($period, $source->period);
        self::assertSame('account-7', $source->authorId?->toString());
    }

    private static function timezone(): ReportTimeZone
    {
        return ReportTimeZone::fromName('Europe/Warsaw');
    }
}
