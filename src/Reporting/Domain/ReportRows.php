<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Domain;

final class ReportRows
{
    /** @var list<ReportRowAccumulator> */
    private array $rows = [];

    /** @param list<int> $dayNumbers */
    public function add(WorklogEntry $entry, array $dayNumbers): void
    {
        foreach ($this->rows as $row) {
            if ($row->belongsTo($entry->issue)) {
                $row->add($entry);

                return;
            }
        }

        $row = new ReportRowAccumulator($entry, $dayNumbers);
        $row->add($entry);
        $this->rows[] = $row;
    }

    /** @return list<ReportRow> */
    public function sorted(): array
    {
        usort($this->rows, static fn(ReportRowAccumulator $left, ReportRowAccumulator $right): int => strnatcmp(
            $left->issue()->toString(),
            $right->issue()->toString(),
        ));

        return array_map(static fn(ReportRowAccumulator $row): ReportRow => $row->toReportRow(), $this->rows);
    }
}
