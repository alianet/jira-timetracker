<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\TimeTracking\Infrastructure\Jira;

use App\TimeTracking\Application\Query\DailyIssueGrouper;
use App\TimeTracking\Infrastructure\Jira\JiraDailyOverviewDirectory;
use App\TimeTracking\Infrastructure\Jira\JiraTransport;
use PHPUnit\Framework\TestCase;

final class JiraDailyOverviewDirectoryTest extends TestCase
{
    public function testReturnsLatestReportedDayAndAssignedIssuesForConfiguredStatuses(): void
    {
        $transport = new DailyTransport();
        $overview = new JiraDailyOverviewDirectory(
            $transport,
            'https://site.test/',
            ['TO DO', 'IN PROGRESS'],
            17,
            new DailyIssueGrouper(),
        )->get();

        self::assertSame('2026-09-15', $overview->lastReportedDate);
        self::assertSame(['APP-1', 'APP-2'], array_map(static fn($issue) => $issue->key->toString(), $overview->lastReportedIssues));
        self::assertSame(['1h 30m', '15m'], array_map(static fn($issue) => $issue->timeSpent, $overview->lastReportedIssues));
        self::assertSame("First paragraph\nSecond paragraph", $overview->lastReportedIssues[0]->description);
        self::assertSame(['APP-3', 'APP-4'], array_map(static fn($issue) => $issue->key->toString(), $overview->assignedIssues));
        self::assertSame('2h', $overview->assignedIssues[0]->timeSpent);
        self::assertSame('IN PROGRESS', $overview->assignedIssues[0]->status);
        self::assertSame('https://site.test/browse/APP-3', $overview->assignedIssues[0]->url);
        self::assertTrue($overview->assignedIssues[1]->unassigned);
        self::assertSame(42, $overview->assignedIssueGroups[0]->sprint?->id);
        self::assertSame(['APP-3', 'APP-4'], array_map(static fn($issue) => $issue->key->toString(), $overview->assignedIssueGroups[0]->issues));
        self::assertNotNull($overview->assignedIssueGroups[0]->sprint);
        self::assertTrue($overview->assignedIssueGroups[0]->sprint->primary);
        self::assertContains(
            'assignee = currentUser() AND status IN ("TO DO", "IN PROGRESS") ORDER BY updated DESC',
            $transport->jql,
        );
        self::assertContains(
            'assignee IS EMPTY AND status IN ("TO DO", "IN PROGRESS") ORDER BY updated DESC',
            $transport->jql,
        );
        self::assertContains('/rest/api/3/field', $transport->paths);
        self::assertContains('/rest/agile/1.0/board/17/sprint', $transport->paths);
        self::assertContains('summary,description,status,timespent,customfield_10020', $transport->fields);
    }
}

final class DailyTransport implements JiraTransport
{
    /** @var list<string> */
    public array $jql = [];
    /** @var list<string> */
    public array $paths = [];
    /** @var list<string> */
    public array $fields = [];

    public function request(string $method, string $path, ?array $body = null, ?array $query = null): array
    {
        $this->paths[] = $path;
        if ($path === '/rest/api/3/myself') {
            return ['accountId' => 'me'];
        }
        if ($path === '/rest/api/3/field') {
            return [[
                'id' => 'customfield_10020',
                'schema' => ['custom' => 'com.pyxis.greenhopper.jira:gh-sprint'],
            ]];
        }
        if ($path === '/rest/agile/1.0/board/17/sprint') {
            return ['values' => [[
                'id' => 42,
                'name' => 'Team Sprint',
                'state' => 'active',
                'endDate' => '2026-09-30T12:00:00.000+0000',
            ]], 'isLast' => true];
        }
        if ($path === '/rest/api/3/issue/APP-1/worklog') {
            return ['total' => 2, 'worklogs' => [
                ['author' => ['accountId' => 'me'], 'started' => '2026-09-15T09:00:00.000+0000', 'timeSpentSeconds' => 3600],
                ['author' => ['accountId' => 'me'], 'started' => '2026-09-15T11:00:00.000+0000', 'timeSpentSeconds' => 1800],
            ]];
        }

        $jql = is_string($query['jql'] ?? null) ? $query['jql'] : '';
        $fields = is_string($query['fields'] ?? null) ? $query['fields'] : '';
        $this->jql[] = $jql;
        $this->fields[] = $fields;
        if (str_starts_with($jql, 'assignee =')) {
            return ['issues' => [[
                'key' => 'APP-3',
                'fields' => [
                    'summary' => 'Assigned issue',
                    'description' => null,
                    'timespent' => 7200,
                    'status' => ['name' => 'IN PROGRESS'],
                    'customfield_10020' => [
                        ['id' => 41, 'name' => 'Closed sprint', 'state' => 'closed', 'originBoardId' => 17],
                        ['id' => 42, 'name' => 'Team Sprint', 'state' => 'active', 'endDate' => '2026-09-30T12:00:00.000+0000'],
                    ],
                ],
            ]]];
        }
        if (str_starts_with($jql, 'assignee IS EMPTY')) {
            return ['issues' => [
                [
                    'key' => 'APP-4',
                    'fields' => [
                        'summary' => 'Unassigned team issue',
                        'description' => null,
                        'timespent' => null,
                        'status' => ['name' => 'TO DO'],
                        'customfield_10020' => [
                            ['id' => 42, 'name' => 'Team Sprint', 'state' => 'active', 'endDate' => '2026-09-30T12:00:00.000+0000'],
                        ],
                    ],
                ],
                [
                    'key' => 'OTHER-1',
                    'fields' => [
                        'summary' => 'Unassigned external issue',
                        'description' => null,
                        'timespent' => null,
                        'status' => ['name' => 'TO DO'],
                        'customfield_10020' => [
                            ['id' => 43, 'name' => 'External Sprint', 'state' => 'active', 'originBoardId' => 18],
                        ],
                    ],
                ],
            ]];
        }

        return ['issues' => [
            [
                'key' => 'APP-1',
                'fields' => [
                    'summary' => 'First issue',
                    'description' => ['type' => 'doc', 'content' => [
                        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'First paragraph']]],
                        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Second paragraph']]],
                    ]],
                    'worklog' => ['total' => 2, 'worklogs' => [['id' => 'partial']]],
                ],
            ],
            [
                'key' => 'APP-2',
                'fields' => [
                    'summary' => 'Second issue',
                    'description' => null,
                    'worklog' => ['total' => 1, 'worklogs' => [[
                        'author' => ['accountId' => 'me'],
                        'started' => '2026-09-15T12:00:00.000+0000',
                        'timeSpentSeconds' => 900,
                    ]]],
                ],
            ],
        ]];
    }
}
