<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Infrastructure\Jira;

use App\Kernel\Support\ApiValue;
use App\TimeTracking\Application\Query\DailyIssue;
use App\TimeTracking\Application\Query\DailyOverview;
use App\TimeTracking\Application\Query\DailyOverviewDirectory;
use App\TimeTracking\Domain\Model\IssueKey;

final readonly class JiraDailyOverviewDirectory implements DailyOverviewDirectory
{
    private const array WORKLOG_WINDOWS = ['-31d', '-366d', null];

    /** @param list<string> $statuses */
    public function __construct(
        private JiraTransport $transport,
        private string $siteUrl,
        private array $statuses,
    ) {}

    public function get(): DailyOverview
    {
        [$date, $reportedIssues] = $this->lastReportedIssues();

        return new DailyOverview($date, $reportedIssues, $this->assignedIssues(), $this->statuses);
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

    /** @return list<DailyIssue> */
    private function assignedIssues(): array
    {
        $quotedStatuses = array_map(
            static fn(string $status): string => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $status) . '"',
            $this->statuses,
        );
        $jql = 'assignee = currentUser() AND status IN (' . implode(', ', $quotedStatuses) . ') ORDER BY updated DESC';
        $issues = $this->searchAll($jql, 'summary,description,status,timespent');

        return array_map(fn(array $issue): DailyIssue => $this->mapIssue(
            $issue,
            ApiValue::intValue(ApiValue::object($issue['fields'] ?? null)['timespent'] ?? null),
            true,
        ), $issues);
    }

    /**
     * @param array<string, mixed> $issue
     */
    private function mapIssue(array $issue, int $seconds, bool $withStatus = false): DailyIssue
    {
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
        );
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
