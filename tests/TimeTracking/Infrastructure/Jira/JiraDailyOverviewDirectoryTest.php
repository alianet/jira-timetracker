<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\TimeTracking\Infrastructure\Jira;

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
        )->get();

        self::assertSame('2026-09-15', $overview->lastReportedDate);
        self::assertSame(['APP-1', 'APP-2'], array_map(static fn($issue) => $issue->key->toString(), $overview->lastReportedIssues));
        self::assertSame(['1h 30m', '15m'], array_map(static fn($issue) => $issue->timeSpent, $overview->lastReportedIssues));
        self::assertSame("First paragraph\nSecond paragraph", $overview->lastReportedIssues[0]->description);
        self::assertSame('APP-3', $overview->assignedIssues[0]->key->toString());
        self::assertSame('2h', $overview->assignedIssues[0]->timeSpent);
        self::assertSame('IN PROGRESS', $overview->assignedIssues[0]->status);
        self::assertSame('https://site.test/browse/APP-3', $overview->assignedIssues[0]->url);
        self::assertContains(
            'assignee = currentUser() AND status IN ("TO DO", "IN PROGRESS") ORDER BY updated DESC',
            $transport->jql,
        );
    }
}

final class DailyTransport implements JiraTransport
{
    /** @var list<string> */
    public array $jql = [];

    public function request(string $method, string $path, ?array $body = null, ?array $query = null): array
    {
        if ($path === '/rest/api/3/myself') {
            return ['accountId' => 'me'];
        }
        if ($path === '/rest/api/3/issue/APP-1/worklog') {
            return ['total' => 2, 'worklogs' => [
                ['author' => ['accountId' => 'me'], 'started' => '2026-09-15T09:00:00.000+0000', 'timeSpentSeconds' => 3600],
                ['author' => ['accountId' => 'me'], 'started' => '2026-09-15T11:00:00.000+0000', 'timeSpentSeconds' => 1800],
            ]];
        }

        $jql = is_string($query['jql'] ?? null) ? $query['jql'] : '';
        $this->jql[] = $jql;
        if (str_starts_with($jql, 'assignee =')) {
            return ['issues' => [[
                'key' => 'APP-3',
                'fields' => [
                    'summary' => 'Assigned issue',
                    'description' => null,
                    'timespent' => 7200,
                    'status' => ['name' => 'IN PROGRESS'],
                ],
            ]]];
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
