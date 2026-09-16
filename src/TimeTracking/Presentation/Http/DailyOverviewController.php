<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Presentation\Http;

use App\Kernel\Presentation\Http\JsonResponse;
use App\TimeTracking\Application\Query\DailyIssue;
use App\TimeTracking\Application\Query\GetDailyOverviewHandler;

final readonly class DailyOverviewController
{
    public function __construct(private GetDailyOverviewHandler $handler) {}

    public function show(): JsonResponse
    {
        $overview = $this->handler->handle();

        return new JsonResponse([
            'lastReportedDate' => $overview->lastReportedDate,
            'lastReportedIssues' => array_map($this->mapIssue(...), $overview->lastReportedIssues),
            'assignedIssues' => array_map($this->mapIssue(...), $overview->assignedIssues),
            'statuses' => $overview->statuses,
        ]);
    }

    /** @return array{key: string, summary: string, description: string, timeSpent: string, url: string, status: string} */
    private function mapIssue(DailyIssue $issue): array
    {
        return [
            'key' => $issue->key->toString(),
            'summary' => $issue->summary,
            'description' => $issue->description,
            'timeSpent' => $issue->timeSpent,
            'url' => $issue->url,
            'status' => $issue->status,
        ];
    }
}
