<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\TimeTracking\Presentation\Http;

use App\TimeTracking\Application\Query\DailyIssue;
use App\TimeTracking\Application\Query\DailyIssueGroup;
use App\TimeTracking\Application\Query\DailyOverview;
use App\TimeTracking\Application\Query\DailyOverviewDirectory;
use App\TimeTracking\Application\Query\DailySprint;
use App\TimeTracking\Application\Query\GetDailyOverviewHandler;
use App\TimeTracking\Domain\Model\IssueKey;
use App\TimeTracking\Presentation\Http\DailyOverviewController;
use PHPUnit\Framework\TestCase;

final class DailyOverviewControllerTest extends TestCase
{
    public function testMapsOverviewToPublicJsonShape(): void
    {
        $response = new DailyOverviewController(new GetDailyOverviewHandler(new DailyDirectory()))->show();

        self::assertSame('2026-09-15', $response->data['lastReportedDate']);
        self::assertSame(['TO DO'], $response->data['statuses']);
        self::assertSame([[
            'key' => 'APP-7',
            'summary' => 'Summary',
            'description' => 'Description',
            'timeSpent' => '1h',
            'url' => 'https://site.test/browse/APP-7',
            'status' => '',
            'unassigned' => false,
        ]], $response->data['lastReportedIssues']);
        self::assertSame([], $response->data['assignedIssues']);
        self::assertSame([[
            'sprint' => [
                'id' => 17,
                'name' => 'Team Sprint',
                'state' => 'active',
                'originBoardId' => 17,
                'primary' => true,
                'startDate' => '2026-09-01T09:00:00.000+0000',
                'endDate' => '2026-09-15T09:00:00.000+0000',
            ],
            'issues' => [],
        ]], $response->data['assignedIssueGroups']);
        self::assertSame('no-store', $response->headers['Cache-Control']);
    }
}

final class DailyDirectory implements DailyOverviewDirectory
{
    public function get(): DailyOverview
    {
        return new DailyOverview('2026-09-15', [new DailyIssue(
            IssueKey::fromString('APP-7'),
            'Summary',
            'Description',
            '1h',
            'https://site.test/browse/APP-7',
        )], [], ['TO DO'], [new DailyIssueGroup(new DailySprint(
            17,
            'Team Sprint',
            'active',
            17,
            true,
            '2026-09-01T09:00:00.000+0000',
            '2026-09-15T09:00:00.000+0000',
        ), [])]);
    }
}
