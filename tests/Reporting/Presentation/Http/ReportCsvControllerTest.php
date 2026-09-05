<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Reporting\Presentation\Http;

use App\Kernel\Exception\ApplicationRuntimeException;
use App\Kernel\Presentation\Http\Request;
use App\Reporting\Application\ExportedFile;
use App\Reporting\Application\ExportMonthlyReportHandler;
use App\Reporting\Application\Port\ReportExporter;
use App\Reporting\Application\Port\WorklogReportSource;
use App\Reporting\Application\WorklogReportData;
use App\Reporting\Domain\ReportPeriod;
use App\Reporting\Domain\ReportTimeZone;
use App\Reporting\Domain\WorklogAuthorId;
use App\Reporting\Presentation\Http\ReportCsvController;
use PHPUnit\Framework\TestCase;

final class ReportCsvControllerTest extends TestCase
{
    public function testMapsHttpQueryAndBuildsCsvResponse(): void
    {
        $source = new class implements WorklogReportSource {
            public ?ReportPeriod $period = null;
            public ?WorklogAuthorId $authorId = null;

            public function forUser(ReportPeriod $period, ?WorklogAuthorId $authorId = null): WorklogReportData
            {
                $this->period = $period;
                $this->authorId = $authorId;

                return new WorklogReportData([], 'acc', 'User', '', false);
            }
        };
        $exporter = new class implements ReportExporter {
            public function export(WorklogReportData $report, ReportPeriod $period): ExportedFile
            {
                return new ExportedFile("CSV\n", 'raport-2026-08.csv');
            }
        };
        $handler = new ExportMonthlyReportHandler($source, $exporter);
        $controller = new ReportCsvController(
            static fn(array &$session): ExportMonthlyReportHandler => $handler,
            true,
            'disabled',
            ReportTimeZone::fromName('Europe/Warsaw'),
        );
        $session = [];

        $response = $controller->export(new Request(
            'GET',
            '/export.csv',
            ['year' => '2026', 'month' => '8', 'accountId' => 'user-2'],
            [],
            [],
            $session,
        ));

        self::assertSame(2026, $source->period?->year);
        self::assertSame(8, $source->period?->month);
        self::assertSame('user-2', $source->authorId?->toString());
        self::assertSame("CSV\n", $response->body);
        self::assertSame('text/csv; charset=UTF-8', $response->headers['Content-Type']);
        self::assertSame('attachment; filename="raport-2026-08.csv"', $response->headers['Content-Disposition']);
        self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
    }

    public function testDisabledExportKeepsExistingErrorBeforeResolvingHandler(): void
    {
        $controller = new ReportCsvController(
            static function (array &$session): ExportMonthlyReportHandler {
                self::fail('Disabled export must not resolve its handler.');
            },
            false,
            'Eksport raportu jest wyłączony.',
            ReportTimeZone::fromName('Europe/Warsaw'),
        );
        $session = [];

        $this->expectException(ApplicationRuntimeException::class);
        $this->expectExceptionMessage('Eksport raportu jest wyłączony.');

        $controller->export(new Request('GET', '/export.csv', [], [], [], $session));
    }

    public function testRejectsInvalidAccountIdAtHttpBoundary(): void
    {
        $controller = new ReportCsvController(
            static function (array &$session): ExportMonthlyReportHandler {
                self::fail('Invalid accountId must be rejected before resolving the handler.');
            },
            true,
            'disabled',
            ReportTimeZone::fromName('Europe/Warsaw'),
        );
        $session = [];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid worklog author identifier.');

        $controller->export(new Request('GET', '/export.csv', ['accountId' => 'user" OR created IS NOT EMPTY'], [], [], $session));
    }
}
