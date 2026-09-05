<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Config;

use App\Kernel\Exception\ReportExportConfigException;

use function Safe\json_decode;

final readonly class ReportExportConfig
{
    /**
     * @param list<string> $columns
     * @param list<string> $summaryRow
     */
    public function __construct(
        public bool $enabled,
        public array $columns,
        public string $filenameMask,
        public bool $bom,
        public array $summaryRow,
        public bool $summarySpacer,
    ) {}

    public static function fromConfig(Config $config): self
    {
        $enabled = self::booleanOrDefault($config, 'REPORT_EXPORT_ENABLED', false);

        if (!$enabled) {
            return new self(false, [], '', false, [], false);
        }

        $columns = array_values(array_filter(
            array_map(trim(...), explode(',', self::required($config, 'REPORT_EXPORT_COLUMNS'))),
            static fn(string $column): bool => $column !== '',
        ));
        if ($columns === []) {
            throw ReportExportConfigException::invalid('REPORT_EXPORT_COLUMNS', 'lista co najmniej jednej kolumny');
        }

        try {
            $summary = json_decode(self::required($config, 'REPORT_EXPORT_SUMMARY_ROW'), true);
        } catch (\Throwable $exception) {
            throw ReportExportConfigException::invalid('REPORT_EXPORT_SUMMARY_ROW', 'tablica JSON', $exception);
        }
        if (!is_array($summary) || !array_is_list($summary)) {
            throw ReportExportConfigException::invalid('REPORT_EXPORT_SUMMARY_ROW', 'tablica JSON');
        }
        foreach ($summary as $cell) {
            if (!is_string($cell)) {
                throw ReportExportConfigException::invalid('REPORT_EXPORT_SUMMARY_ROW', 'tablica tekstowych komórek JSON');
            }
        }

        return new self(
            true,
            $columns,
            self::required($config, 'REPORT_EXPORT_FILENAME'),
            self::boolean($config, 'REPORT_EXPORT_BOM'),
            $summary,
            self::boolean($config, 'REPORT_EXPORT_SUMMARY_SPACER'),
        );
    }

    private static function required(Config $config, string $name): string
    {
        return $config->nullable($name) ?? throw ReportExportConfigException::missing($name);
    }

    private static function boolean(Config $config, string $name): bool
    {
        $value = self::required($config, $name);
        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $parsed ?? throw ReportExportConfigException::invalid($name, 'true albo false');
    }

    private static function booleanOrDefault(Config $config, string $name, bool $default): bool
    {
        $value = $config->nullable($name);

        if ($value === null) {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $parsed ?? throw ReportExportConfigException::invalid($name, 'true albo false');
    }
}
