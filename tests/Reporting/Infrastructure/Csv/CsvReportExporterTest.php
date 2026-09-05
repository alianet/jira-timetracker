<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Reporting\Infrastructure\Csv;

use App\Kernel\Config\ReportExportConfig;
use App\Kernel\Infrastructure\Translation\TranslatorFactory;
use App\Reporting\Application\WorklogReportData;
use App\Reporting\Domain\IssueKey;
use App\Reporting\Domain\ReportPeriod;
use App\Reporting\Domain\ReportTimeZone;
use App\Reporting\Domain\WorklogDate;
use App\Reporting\Domain\WorklogDuration;
use App\Reporting\Domain\WorklogEntry;
use App\Reporting\Infrastructure\Csv\CsvReportExporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CsvReportExporterTest extends TestCase
{
    #[DataProvider('escapedValues')]
    public function testEscapesSpecialAndEmptyValues(string $value, string $expectedCell): void
    {
        $exporter = $this->exporter(['comment']);
        $report = $this->report(new WorklogEntry(
            '1',
            WorklogDate::fromString('2026-08-31', self::timezone()),
            IssueKey::fromString('APP-1'),
            'Projekt',
            'Temat',
            WorklogDuration::fromSeconds(60),
            $value,
        ));

        $file = $exporter->export($report, new ReportPeriod(2026, 8, self::timezone()));

        self::assertSame("Komentarz\n{$expectedCell}\n", $file->content);
    }

    /** @return iterable<string, array{string, string}> */
    public static function escapedValues(): iterable
    {
        yield 'separator' => ['część;druga', '"część;druga"'];
        yield 'quotes' => ['tekst "cytowany"', '"tekst ""cytowany"""'];
        yield 'new line' => ["pierwsza\ndruga", "\"pierwsza\ndruga\""];
        yield 'empty value' => ['', ''];
        yield 'Unicode' => ['Zażółć gęślą jaźń — 東京', '"Zażółć gęślą jaźń — 東京"'];
        yield 'formula' => ['=HYPERLINK("https://evil.test")', '"\'=HYPERLINK(""https://evil.test"")"'];
        yield 'plus prefix' => ['+1+1', "'+1+1"];
        yield 'minus prefix' => ['-1+1', "'-1+1"];
        yield 'at prefix' => ['@SUM(A1:A2)', "'@SUM(A1:A2)"];
        yield 'tab prefix' => ["\t=1+1", "\"'\t=1+1\""];
        yield 'carriage return prefix' => ["\r=1+1", "\"'\r=1+1\""];
    }

    public function testNeutralizesFormulaPrefixesInIssueSummaryAndComment(): void
    {
        $report = $this->report(new WorklogEntry(
            '1',
            WorklogDate::fromString('2026-08-31', self::timezone()),
            IssueKey::fromString('APP-1'),
            'Projekt',
            '=HYPERLINK("https://evil.test/summary")',
            WorklogDuration::fromSeconds(60),
            '@SUM(A1:A2)',
        ));

        $file = $this->exporter(['summary', 'comment'])->export(
            $report,
            new ReportPeriod(2026, 8, self::timezone()),
        );

        self::assertSame(
            "\"Podsumowanie zgłoszenia\";Komentarz\n"
            . "\"'=HYPERLINK(\"\"https://evil.test/summary\"\")\";'@SUM(A1:A2)\n",
            $file->content,
        );
    }

    public function testPreservesConfiguredColumnsBomSummaryFormattingAndFilename(): void
    {
        $config = new ReportExportConfig(
            true,
            ['issue', 'project', 'summary', 'date', 'minutes', 'hours', 'time_spent', 'comment', 'url', 'worklog_id'],
            'czas-{username}-{year}_{month}.csv',
            true,
            ['Razem', '{total_minutes}', '{total_hours}h {total_remaining_minutes}m', '{total_decimal_hours}'],
            true,
        );
        $report = $this->report(new WorklogEntry(
            '41',
            WorklogDate::fromString('2026-08-31', self::timezone()),
            IssueKey::fromString('APP-7'),
            'Projekt',
            'Temat; z separatorem',
            WorklogDuration::fromSeconds(5400),
            "Pierwsza linia\nDruga \"cytowana\"",
        ));

        $file = $this->exporter(config: $config)->export($report, new ReportPeriod(2026, 8, self::timezone()));

        self::assertSame('czas-Jan-Kowalski-2026_08.csv', $file->filename);
        self::assertSame(
            "\xEF\xBB\xBF\"Klucz zgłoszenia\";Projekt;\"Podsumowanie zgłoszenia\";Data;\"Czas w minutach\";\"Czas w godzinach\";Czas;Komentarz;\"Adres zgłoszenia\";\"ID worklogu\"\n"
            . "APP-7;Projekt;\"Temat; z separatorem\";2026-08-31;90;1.5;\"1h 30m\";\"Pierwsza linia\nDruga \"\"cytowana\"\"\";https://site.test/browse/APP-7;41\n"
            . "\nRazem;90;\"1h 30m\";1.50\n",
            $file->content,
        );
    }

    /** @param list<string> $columns */
    private function exporter(array $columns = [], ?ReportExportConfig $config = null): CsvReportExporter
    {
        $config ??= new ReportExportConfig(true, $columns, '{username}-{year}-{month}.csv', false, [], false);
        $translator = new TranslatorFactory()->create(dirname(__DIR__, 4) . '/translations', ['pl', 'en'], 'pl');

        return new CsvReportExporter($config, $translator, static fn(string $issue): string => 'https://site.test/browse/' . $issue);
    }

    private function report(WorklogEntry $entry): WorklogReportData
    {
        return new WorklogReportData([$entry], 'acc-1', 'Jan Kowalski', '', true);
    }

    private static function timezone(): ReportTimeZone
    {
        return ReportTimeZone::fromName('Europe/Warsaw');
    }
}
