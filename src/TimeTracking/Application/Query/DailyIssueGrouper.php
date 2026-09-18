<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Application\Query;

final readonly class DailyIssueGrouper
{
    /**
     * @param list<DailyIssue> $issues
     * @param array<int, DailySprint> $sprints
     * @return list<DailyIssueGroup>
     */
    public function group(array $issues, array $sprints): array
    {
        $groupedIssues = [];
        $withoutSprint = [];

        foreach ($issues as $issue) {
            $matched = false;
            foreach ($issue->sprintIds as $sprintId) {
                if (!isset($sprints[$sprintId])) {
                    continue;
                }

                $groupedIssues[$sprintId][] = $issue;
                $matched = true;
            }

            if (!$matched) {
                $withoutSprint[] = $issue;
            }
        }

        $groupedSprints = array_intersect_key($sprints, $groupedIssues);
        uasort($groupedSprints, $this->compareSprints(...));

        $groups = [];
        foreach ($groupedSprints as $sprintId => $sprint) {
            $groups[] = new DailyIssueGroup($sprint, $groupedIssues[$sprintId]);
        }
        if ($withoutSprint !== []) {
            $groups[] = new DailyIssueGroup(null, $withoutSprint);
        }

        return $groups;
    }

    private function compareSprints(DailySprint $left, DailySprint $right): int
    {
        $leftOrder = [
            $this->stateOrder($left->state),
            $left->primary ? 0 : 1,
            $this->dateOrder($left),
            $left->id,
        ];
        $rightOrder = [
            $this->stateOrder($right->state),
            $right->primary ? 0 : 1,
            $this->dateOrder($right),
            $right->id,
        ];

        return $leftOrder <=> $rightOrder;
    }

    private function stateOrder(string $state): int
    {
        return match ($state) {
            'active' => 0,
            'future' => 1,
            default => 2,
        };
    }

    private function dateOrder(DailySprint $sprint): string
    {
        return $sprint->state === 'active'
            ? $sprint->endDate ?? ''
            : $sprint->startDate ?? '';
    }
}
