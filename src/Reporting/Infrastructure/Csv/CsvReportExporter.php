<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Infrastructure\Csv;

use App\Kernel\Config\ReportExportConfig;
use App\Kernel\Exception\ApplicationRuntimeException as WorkLogRuntimeException;
use App\Reporting\Application\ExportedFile;
use App\Reporting\Application\Port\ReportExporter;
use App\Reporting\Application\WorklogReportData;
use App\Reporting\Domain\ReportPeriod;
use App\Reporting\Domain\WorklogEntry;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Safe\fclose;
use function Safe\fopen;
use function Safe\fputcsv;
use function Safe\fwrite;
use function Safe\preg_replace;
use function Safe\rewind;
use function Safe\stream_get_contents;

final readonly class CsvReportExporter implements ReportExporter
{
    private const array FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    private const array COLUMNS = [
        'issue' => ['csv.issue', 'issue'],
        'project' => ['csv.project', 'project'],
        'summary' => ['csv.summary', 'summary'],
        'date' => ['csv.date', 'date'],
        'minutes' => ['csv.minutes', 'minutes'],
        'hours' => ['csv.hours', 'hours'],
        'time_spent' => ['csv.time_spent', 'timeSpent'],
        'comment' => ['csv.comment', 'comment'],
        'url' => ['csv.url', 'url'],
        'worklog_id' => ['csv.worklog_id', 'id'],
    ];

    /** @var \Closure(string): string */
    private \Closure $issueUrl;

    /** @param callable(string): string $issueUrl */
    public function __construct(
        private ReportExportConfig $config,
        private TranslatorInterface $translator,
        callable $issueUrl,
    ) {
        $this->issueUrl = $issueUrl(...);
    }

    public function export(WorklogReportData $report, ReportPeriod $period): ExportedFile
    {
        $columns = $this->columns();
        $totalMinutes = 0;

        try {
            $stream = fopen('php://temp', 'w+');
        } catch (\Throwable) {
            throw WorkLogRuntimeException::create($this->translator->trans('csv.create_failed'));
        }

        if ($this->config->bom) {
            fwrite($stream, "\xEF\xBB\xBF");
        }
        $this->writeRow($stream, array_column($columns, 0));

        foreach ($report->entries as $entry) {
            $minutes = (int) round($entry->duration->toSeconds() / 60);
            $totalMinutes += $minutes;
            $values = $this->entryValues($entry, $minutes);
            $this->writeRow($stream, array_map(
                static fn(array $column): int|float|string => $values[$column[1]],
                $columns,
            ));
        }

        if ($this->config->summarySpacer && $this->config->summaryRow !== []) {
            $this->writeRow($stream, []);
        }
        if ($this->config->summaryRow !== []) {
            $this->writeRow($stream, $this->summaryRow($totalMinutes));
        }

        try {
            rewind($stream);
            $csv = stream_get_contents($stream);
            fclose($stream);
        } catch (\Throwable) {
            throw WorkLogRuntimeException::create($this->translator->trans('csv.read_failed'));
        }

        return new ExportedFile($csv, $this->filename($report->displayName, $period));
    }

    /** @return list<array{string, string}> */
    private function columns(): array
    {
        $columns = [];
        foreach ($this->config->columns as $name) {
            if (!isset(self::COLUMNS[$name])) {
                throw WorkLogRuntimeException::create($this->translator->trans('csv.unknown_column', [
                    '{name}' => $name,
                    '{allowed}' => implode(', ', array_keys(self::COLUMNS)),
                ]));
            }
            $columns[] = [$this->translator->trans(self::COLUMNS[$name][0]), self::COLUMNS[$name][1]];
        }

        return $columns;
    }

    /** @return array<string, int|float|string> */
    private function entryValues(WorklogEntry $entry, int $minutes): array
    {
        return [
            'id' => $entry->id,
            'date' => $entry->date->toString(),
            'issue' => $entry->issue->toString(),
            'project' => $entry->project,
            'summary' => $entry->summary,
            'minutes' => $minutes,
            'hours' => round($entry->duration->toSeconds() / 3600, 2),
            'comment' => $entry->comment,
            'timeSpent' => $this->formatSeconds($entry->duration->toSeconds()),
            'url' => ($this->issueUrl)($entry->issue->toString()),
        ];
    }

    /** @return list<string> */
    private function summaryRow(int $totalMinutes): array
    {
        $replacements = [
            '{total_minutes}' => (string) $totalMinutes,
            '{total_hours}' => (string) intdiv($totalMinutes, 60),
            '{total_remaining_minutes}' => (string) ($totalMinutes % 60),
            '{total_decimal_hours}' => number_format($totalMinutes / 60, 2, '.', ''),
        ];

        return array_map(static fn(string $cell): string => strtr($cell, $replacements), $this->config->summaryRow);
    }

    private function filename(string $username, ReportPeriod $period): string
    {
        $filename = strtr($this->config->filenameMask, [
            '{username}' => $username,
            '{year}' => sprintf('%04d', $period->year),
            '{month}' => sprintf('%02d', $period->month),
        ]);
        $filename = trim(preg_replace('/[^\pL\pN._-]+/u', '-', $filename), '.-');

        if ($filename === '') {
            throw WorkLogRuntimeException::create($this->translator->trans('csv.invalid_filename'));
        }

        return $filename;
    }

    private function formatSeconds(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $hoursString = $hours > 0 ? "{$hours}h " : '';
        $minutesString = $minutes > 0 ? "{$minutes}m" : '';

        return trim($hoursString . $minutesString ?: '0m');
    }

    /**
     * @param resource $stream
     * @param array<int|string, bool|float|int|string|null> $fields
     */
    private function writeRow($stream, array $fields): void
    {
        try {
            fputcsv($stream, array_map(self::neutralizeFormula(...), $fields), ';', '"', '');
        } catch (\Throwable) {
            throw WorkLogRuntimeException::create($this->translator->trans('csv.write_failed'));
        }
    }

    private static function neutralizeFormula(bool|float|int|string|null $field): bool|float|int|string|null
    {
        if (!is_string($field) || $field === '' || !in_array($field[0], self::FORMULA_PREFIXES, true)) {
            return $field;
        }

        return "'" . $field;
    }
}
