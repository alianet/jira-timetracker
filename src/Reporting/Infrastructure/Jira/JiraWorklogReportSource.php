<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Infrastructure\Jira;

use App\Reporting\Application\Port\WorklogReportSource;
use App\Reporting\Application\WorklogReportData;
use App\Reporting\Domain\IssueKey;
use App\Reporting\Domain\ReportPeriod;
use App\Reporting\Domain\ReportTimeZone;
use App\Reporting\Domain\WorklogAuthorId;
use App\Reporting\Domain\WorklogDate;
use App\Reporting\Domain\WorklogDuration;
use App\Reporting\Domain\WorklogEntry;

final readonly class JiraWorklogReportSource implements WorklogReportSource
{
    public function __construct(
        private JiraReportClient $jira,
        private ReportTimeZone $timezone,
    ) {}

    public function forUser(ReportPeriod $period, ?WorklogAuthorId $authorId = null): WorklogReportData
    {
        $jiraReport = $this->jira->worklogReport($period->year, $period->month, $authorId);
        $entries = [];
        foreach ($jiraReport->entries as $entry) {
            $entries[] = new WorklogEntry(
                $entry->id,
                WorklogDate::fromString($entry->date, $this->timezone),
                IssueKey::fromString($entry->issue),
                $entry->project,
                $entry->summary,
                WorklogDuration::fromSeconds($entry->seconds),
                $entry->comment,
            );
        }

        return new WorklogReportData(
            entries: $entries,
            accountId: $jiraReport->accountId,
            displayName: $jiraReport->displayName,
            avatarUrl: $jiraReport->avatarUrl,
            isOwnReport: $jiraReport->isOwnReport,
        );
    }
}
