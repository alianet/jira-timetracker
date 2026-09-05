<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Kernel\Config;

use App\Kernel\Config\ReportExportConfig;
use Tests\TestCase;

final class ReportExportConfigTest extends TestCase
{
    public function testItReturnsDisabledConfigByDefault(): void
    {
        $config = $this->createConfig([]);

        $exportConfig = ReportExportConfig::fromConfig($config);

        self::assertFalse($exportConfig->enabled);
        self::assertSame([], $exportConfig->columns);
    }

    public function testItParsesEnabledExportConfiguration(): void
    {
        $config = $this->createConfig([
            'REPORT_EXPORT_ENABLED' => 'true',
            'REPORT_EXPORT_COLUMNS' => 'issue,summary,date',
            'REPORT_EXPORT_FILENAME' => 'report-{year}-{month}.csv',
            'REPORT_EXPORT_BOM' => 'false',
            'REPORT_EXPORT_SUMMARY_ROW' => '\'["Total","2h"]\'',
            'REPORT_EXPORT_SUMMARY_SPACER' => 'true',
        ]);

        $exportConfig = ReportExportConfig::fromConfig($config);

        self::assertTrue($exportConfig->enabled);
        self::assertSame(['issue', 'summary', 'date'], $exportConfig->columns);
        self::assertSame('report-{year}-{month}.csv', $exportConfig->filenameMask);
        self::assertFalse($exportConfig->bom);
        self::assertSame(['Total', '2h'], $exportConfig->summaryRow);
        self::assertTrue($exportConfig->summarySpacer);
    }
}
