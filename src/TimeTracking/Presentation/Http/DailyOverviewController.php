<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Presentation\Http;

use App\Kernel\Presentation\Http\JsonResponse;
use App\TimeTracking\Application\Query\DailyIssue;
use App\TimeTracking\Application\Query\DailyIssueGroup;
use App\TimeTracking\Application\Query\DailySprint;
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
            'assignedIssueGroups' => array_map($this->mapGroup(...), $overview->assignedIssueGroups),
            'statuses' => $overview->statuses,
        ]);
    }

    /** @return array{key: string, summary: string, description: string, timeSpent: string, url: string, status: string, unassigned: bool} */
    private function mapIssue(DailyIssue $issue): array
    {
        return [
            'key' => $issue->key->toString(),
            'summary' => $issue->summary,
            'description' => $issue->description,
            'timeSpent' => $issue->timeSpent,
            'url' => $issue->url,
            'status' => $issue->status,
            'unassigned' => $issue->unassigned,
        ];
    }

    /** @return array{sprint: ?array{id: int, name: string, state: string, originBoardId: ?int, primary: bool, startDate: ?string, endDate: ?string}, issues: list<array{key: string, summary: string, description: string, timeSpent: string, url: string, status: string, unassigned: bool}>} */
    private function mapGroup(DailyIssueGroup $group): array
    {
        return [
            'sprint' => $group->sprint === null ? null : $this->mapSprint($group->sprint),
            'issues' => array_map($this->mapIssue(...), $group->issues),
        ];
    }

    /** @return array{id: int, name: string, state: string, originBoardId: ?int, primary: bool, startDate: ?string, endDate: ?string} */
    private function mapSprint(DailySprint $sprint): array
    {
        return [
            'id' => $sprint->id,
            'name' => $sprint->name,
            'state' => $sprint->state,
            'originBoardId' => $sprint->originBoardId,
            'primary' => $sprint->primary,
            'startDate' => $sprint->startDate,
            'endDate' => $sprint->endDate,
        ];
    }
}
