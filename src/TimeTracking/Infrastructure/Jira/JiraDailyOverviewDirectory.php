<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Infrastructure\Jira;

use App\Kernel\Support\ApiValue;
use App\TimeTracking\Application\Query\DailyIssue;
use App\TimeTracking\Application\Query\DailyIssueGrouper;
use App\TimeTracking\Application\Query\DailyOverview;
use App\TimeTracking\Application\Query\DailyOverviewDirectory;
use App\TimeTracking\Application\Query\DailySprint;
use App\TimeTracking\Domain\Model\IssueKey;

use function Safe\preg_match_all;

final readonly class JiraDailyOverviewDirectory implements DailyOverviewDirectory
{
    private const array WORKLOG_WINDOWS = ['-31d', '-366d', null];

    /** @param list<string> $statuses */
    public function __construct(
        private JiraTransport $transport,
        private string $siteUrl,
        private array $statuses,
        private ?int $primaryBoardId,
        private DailyIssueGrouper $issueGrouper,
    ) {}

    public function get(): DailyOverview
    {
        [$date, $reportedIssues] = $this->lastReportedIssues();

        [$assignedIssues, $sprints] = $this->dailyIssues();

        return new DailyOverview(
            $date,
            $reportedIssues,
            $assignedIssues,
            $this->statuses,
            $this->issueGrouper->group($assignedIssues, $sprints),
        );
    }

    /** @return array{?string, list<DailyIssue>} */
    private function lastReportedIssues(): array
    {
        $currentUser = ApiValue::object($this->transport->request('GET', '/rest/api/3/myself'));
        $accountId = ApiValue::stringValue($currentUser['accountId'] ?? null);

        foreach (self::WORKLOG_WINDOWS as $window) {
            $jql = 'worklogAuthor = currentUser()';
            if ($window !== null) {
                $jql .= " AND worklogDate >= {$window}";
            }

            $issues = $this->searchAll($jql, 'summary,description,worklog');
            $entries = $this->latestEntries($issues, $accountId);
            if ($entries !== []) {
                return $entries;
            }
        }

        return [null, []];
    }

    /**
     * @param list<array<string, mixed>> $issues
     * @return array{string, list<DailyIssue>}|array{}
     */
    private function latestEntries(array $issues, string $accountId): array
    {
        $latestDate = null;
        $latest = [];

        foreach ($issues as $issue) {
            $key = ApiValue::stringValue($issue['key'] ?? null);
            $fields = ApiValue::object($issue['fields'] ?? null);
            $worklogPage = ApiValue::object($fields['worklog'] ?? null);
            $worklogs = $this->worklogs($key, $worklogPage);

            foreach ($worklogs as $worklog) {
                $author = ApiValue::object($worklog['author'] ?? null);
                if (ApiValue::stringValue($author['accountId'] ?? null) !== $accountId) {
                    continue;
                }

                $date = substr(ApiValue::stringValue($worklog['started'] ?? null), 0, 10);
                if ($date === '' || ($latestDate !== null && $date < $latestDate)) {
                    continue;
                }
                if ($latestDate === null || $date > $latestDate) {
                    $latestDate = $date;
                    $latest = [];
                }

                if (!isset($latest[$key])) {
                    $latest[$key] = ['issue' => $issue, 'seconds' => 0];
                }
                $latest[$key]['seconds'] += ApiValue::intValue($worklog['timeSpentSeconds'] ?? null);
            }
        }

        if ($latestDate === null) {
            return [];
        }

        $result = [];
        foreach ($latest as $entry) {
            $result[] = $this->mapIssue($entry['issue'], $entry['seconds']);
        }
        usort($result, static fn(DailyIssue $left, DailyIssue $right): int => $left->key->toString() <=> $right->key->toString());

        return [$latestDate, $result];
    }

    /** @return array{list<DailyIssue>, array<int, DailySprint>} */
    private function dailyIssues(): array
    {
        $quotedStatuses = array_map(
            static fn(string $status): string => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $status) . '"',
            $this->statuses,
        );
        $sprintFieldId = $this->sprintFieldId();
        $fields = ['summary', 'description', 'status', 'timespent'];
        if ($sprintFieldId !== null) {
            $fields[] = $sprintFieldId;
        }
        $fields = implode(',', $fields);
        $sprints = $this->primaryActiveSprints();
        $assignedIssues = $this->mapDailyIssues(
            $this->searchAll(
                'assignee = currentUser() AND status IN (' . implode(', ', $quotedStatuses) . ') ORDER BY updated DESC',
                $fields,
            ),
            $sprintFieldId,
            $sprints,
        );
        if ($this->primaryBoardId === null) {
            return [$assignedIssues, $sprints];
        }

        $unassignedIssues = $this->mapDailyIssues(
            $this->searchAll(
                'assignee IS EMPTY AND status IN (' . implode(', ', $quotedStatuses) . ') ORDER BY updated DESC',
                $fields,
            ),
            $sprintFieldId,
            $sprints,
            true,
        );

        return [
            [...$assignedIssues, ...array_values(array_filter(
                $unassignedIssues,
                fn(DailyIssue $issue): bool => $this->hasPrimaryActiveSprint($issue, $sprints),
            ))],
            $sprints,
        ];
    }

    /**
     * @param list<array<string, mixed>> $issues
     * @param array<int, DailySprint> $sprints
     * @return list<DailyIssue>
     */
    private function mapDailyIssues(array $issues, ?string $sprintFieldId, array &$sprints, bool $unassigned = false): array
    {
        $result = [];
        foreach ($issues as $issue) {
            $fields = ApiValue::object($issue['fields'] ?? null);
            $sprintIds = $this->mapIssueSprints(
                $sprintFieldId === null ? null : ($fields[$sprintFieldId] ?? null),
                $sprints,
            );
            $result[] = $this->mapIssue(
                $issue,
                ApiValue::intValue($fields['timespent'] ?? null),
                true,
                $sprintIds,
                $unassigned,
            );
        }

        return $result;
    }

    /** @return array<int, DailySprint> */
    private function primaryActiveSprints(): array
    {
        if ($this->primaryBoardId === null) {
            return [];
        }

        $sprints = [];
        $startAt = 0;
        do {
            $page = $this->transport->request(
                'GET',
                '/rest/agile/1.0/board/' . $this->primaryBoardId . '/sprint',
                null,
                ['state' => 'active', 'startAt' => $startAt, 'maxResults' => 50],
            );
            $batch = $this->objects($page['values'] ?? null);
            foreach ($batch as $rawSprint) {
                $sprint = $this->mapSprint($rawSprint, true);
                if ($sprint !== null) {
                    $sprints[$sprint->id] = $sprint;
                }
            }
            $startAt += count($batch);
        } while ($batch !== [] && !($page['isLast'] ?? true));

        return $sprints;
    }

    /**
     * @param array<string, mixed> $issue
     * @param list<int> $sprintIds
     */
    private function mapIssue(
        array $issue,
        int $seconds,
        bool $withStatus = false,
        array $sprintIds = [],
        bool $unassigned = false,
    ): DailyIssue {
        $key = ApiValue::stringValue($issue['key'] ?? null);
        $fields = ApiValue::object($issue['fields'] ?? null);
        $status = ApiValue::object($fields['status'] ?? null);

        return new DailyIssue(
            IssueKey::fromString($key),
            ApiValue::stringValue($fields['summary'] ?? null),
            $this->plainText(ApiValue::object($fields['description'] ?? null)),
            $this->formatSeconds($seconds),
            rtrim($this->siteUrl, '/') . '/browse/' . $key,
            $withStatus ? ApiValue::stringValue($status['name'] ?? null) : '',
            $sprintIds,
            $unassigned,
        );
    }

    /** @param array<int, DailySprint> $sprints */
    private function hasPrimaryActiveSprint(DailyIssue $issue, array $sprints): bool
    {
        foreach ($issue->sprintIds as $sprintId) {
            $sprint = $sprints[$sprintId] ?? null;
            if ($sprint?->primary && $sprint->state === 'active') {
                return true;
            }
        }

        return false;
    }

    private function sprintFieldId(): ?string
    {
        $fields = $this->transport->request('GET', '/rest/api/3/field');
        foreach ($this->objects($fields) as $field) {
            $schema = ApiValue::object($field['schema'] ?? null);
            if (ApiValue::stringValue($schema['custom'] ?? null) !== 'com.pyxis.greenhopper.jira:gh-sprint') {
                continue;
            }

            $id = ApiValue::stringValue($field['id'] ?? null);
            if ($id !== '') {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param array<int, DailySprint> $sprints
     * @return list<int>
     */
    private function mapIssueSprints(mixed $value, array &$sprints): array
    {
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $rawSprint) {
            $sprint = $this->mapSprint($rawSprint);
            if ($sprint === null) {
                continue;
            }

            if (!isset($sprints[$sprint->id]) || !$sprints[$sprint->id]->primary || $sprint->primary) {
                $sprints[$sprint->id] = $sprint;
            }
            $ids[] = $sprint->id;
        }

        return array_values(array_unique($ids));
    }

    private function mapSprint(mixed $rawSprint, bool $primaryBoardSprint = false): ?DailySprint
    {
        $sprint = is_array($rawSprint)
            ? ApiValue::object($rawSprint)
            : $this->legacySprint(ApiValue::stringValue($rawSprint));
        $id = ApiValue::intValue($sprint['id'] ?? null);
        $state = strtolower(ApiValue::stringValue($sprint['state'] ?? null));
        if ($id <= 0 || !in_array($state, ['active', 'future'], true)) {
            return null;
        }

        $originBoardId = ApiValue::intValue($sprint['originBoardId'] ?? $sprint['rapidViewId'] ?? null);
        $primary = $primaryBoardSprint || ($originBoardId > 0 && $originBoardId === $this->primaryBoardId);

        return new DailySprint(
            $id,
            ApiValue::stringValue($sprint['name'] ?? null),
            $state,
            $originBoardId > 0 ? $originBoardId : ($primaryBoardSprint ? $this->primaryBoardId : null),
            $primary,
            $this->nullableString($sprint['startDate'] ?? null),
            $this->nullableString($sprint['endDate'] ?? null),
        );
    }

    /** @return array<string, string> */
    private function legacySprint(string $value): array
    {
        preg_match_all('/(?:^|,)(id|rapidViewId|state|name|startDate|endDate)=([^,\]]*)/', $value, $matches, PREG_SET_ORDER);
        $sprint = [];
        foreach ($matches as $match) {
            $sprint[$match[1]] = $match[2] === '<null>' ? '' : $match[2];
        }

        return $sprint;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = ApiValue::stringValue($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $worklogPage
     * @return list<array<string, mixed>>
     */
    private function worklogs(string $issueKey, array $worklogPage): array
    {
        $items = $this->objects($worklogPage['worklogs'] ?? null);
        if (ApiValue::intValue($worklogPage['total'] ?? null) <= count($items)) {
            return $items;
        }

        $result = [];
        $startAt = 0;
        do {
            $page = $this->transport->request('GET', "/rest/api/3/issue/{$issueKey}/worklog", null, [
                'startAt' => $startAt,
                'maxResults' => 100,
            ]);
            $batch = $this->objects($page['worklogs'] ?? null);
            array_push($result, ...$batch);
            $startAt += count($batch);
        } while ($batch !== [] && $startAt < ApiValue::intValue($page['total'] ?? null));

        return $result;
    }

    /** @return list<array<string, mixed>> */
    private function searchAll(string $jql, string $fields): array
    {
        $issues = [];
        $nextPageToken = null;
        do {
            $query = ['jql' => $jql, 'fields' => $fields, 'maxResults' => 100];
            if ($nextPageToken !== null) {
                $query['nextPageToken'] = $nextPageToken;
            }
            $page = $this->transport->request('GET', '/rest/api/3/search/jql', null, $query);
            array_push($issues, ...$this->objects($page['issues'] ?? null));
            $token = $page['nextPageToken'] ?? null;
            $nextPageToken = is_string($token) && $token !== '' ? $token : null;
        } while ($nextPageToken !== null);

        return $issues;
    }

    /** @return list<array<string, mixed>> */
    private function objects(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(ApiValue::object(...), array_filter($value, is_array(...))));
    }

    /** @param array<string, mixed> $node */
    private function plainText(array $node): string
    {
        if (($node['type'] ?? '') === 'hardBreak') {
            return "\n";
        }

        $attributes = ApiValue::object($node['attrs'] ?? null);
        $type = ApiValue::stringValue($node['type'] ?? null);
        $text = ApiValue::stringValue($node['text'] ?? match ($type) {
            'mention', 'emoji', 'status' => $attributes['text'] ?? '',
            'inlineCard' => $attributes['url'] ?? '',
            default => '',
        });
        foreach ($this->objects($node['content'] ?? null) as $child) {
            $text .= $this->plainText($child);
        }
        if (in_array($type, ['paragraph', 'heading', 'listItem'], true)) {
            $text .= "\n";
        }

        return $type === 'doc' ? trim($text) : $text;
    }

    private function formatSeconds(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $parts = [];
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes}m";
        }

        return $parts === [] ? '0m' : implode(' ', $parts);
    }
}
