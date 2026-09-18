<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\TimeTracking\Application\Query;

use App\TimeTracking\Application\Query\DailyIssue;
use App\TimeTracking\Application\Query\DailyIssueGrouper;
use App\TimeTracking\Application\Query\DailySprint;
use App\TimeTracking\Domain\Model\IssueKey;
use PHPUnit\Framework\TestCase;

final class DailyIssueGrouperTest extends TestCase
{
    public function testGroupsIssuesAndOrdersPrimarySprintsBeforeOthersInTheSameState(): void
    {
        $issues = [
            $this->issue('APP-1', [10, 20]),
            $this->issue('APP-2', [30]),
            $this->issue('APP-3', []),
            $this->issue('APP-4', [999]),
        ];
        $sprints = [
            10 => new DailySprint(10, 'Other active', 'active', 2, false, null, '2026-09-30'),
            20 => new DailySprint(20, 'Primary active', 'active', 1, true, null, '2026-10-01'),
            30 => new DailySprint(30, 'Primary future', 'future', 1, true, '2026-10-02', null),
        ];

        $groups = new DailyIssueGrouper()->group($issues, $sprints);

        self::assertSame([20, 10, 30, null], array_map(static fn($group) => $group->sprint?->id, $groups));
        self::assertSame(['APP-1'], $this->keys($groups[0]->issues));
        self::assertSame(['APP-1'], $this->keys($groups[1]->issues));
        self::assertSame(['APP-2'], $this->keys($groups[2]->issues));
        self::assertSame(['APP-3', 'APP-4'], $this->keys($groups[3]->issues));
    }

    /** @param list<int> $sprintIds */
    private function issue(string $key, array $sprintIds): DailyIssue
    {
        return new DailyIssue(IssueKey::fromString($key), '', '', '0m', '', '', $sprintIds);
    }

    /** @param list<DailyIssue> $issues @return list<string> */
    private function keys(array $issues): array
    {
        return array_map(static fn(DailyIssue $issue): string => $issue->key->toString(), $issues);
    }
}
