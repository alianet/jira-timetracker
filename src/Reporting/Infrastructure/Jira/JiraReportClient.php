<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Infrastructure\Jira;

use App\Kernel\Exception\ApplicationRuntimeException as WorkLogRuntimeException;
use App\Kernel\Exception\RateLimitExceededException;
use App\Kernel\Support\ApiValue;
use App\Reporting\Domain\WorklogAuthorId;
use App\Shared\Infrastructure\Http\HttpTransportException;
use App\Shared\Infrastructure\Http\JsonHttpTransport;
use App\Shared\Infrastructure\Jira\JiraErrorResponse;
use JsonException;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Safe\strtotime;

final readonly class JiraReportClient
{
    public function __construct(
        private string $siteUrl,
        private JsonHttpTransport $transport,
        private TranslatorInterface $translator,
    ) {}

    /** @throws JsonException */
    public function worklogReport(int $year, int $month, ?WorklogAuthorId $selectedAuthorId = null): JiraWorklogReport
    {
        $startDate = \sprintf('%04d-%02d-01', $year, $month);
        try {
            $endTimestamp = strtotime("{$startDate} +1 month -1 day");
        } catch (\Throwable) {
            throw WorkLogRuntimeException::create($this->translator->trans('jira.report_period_failed'));
        }

        $endDate = \date('Y-m-d', $endTimestamp);
        $currentUser = ApiValue::object($this->request('/rest/api/3/myself'));
        $currentAccountId = ApiValue::stringValue($currentUser['accountId'] ?? null);
        $accountId = $selectedAuthorId?->toString() ?? $currentAccountId;
        $reportUser = $accountId === $currentAccountId
            ? $currentUser
            : ApiValue::object($this->request('/rest/api/3/user', null, 'GET', ['accountId' => $accountId]));
        $escapedAccountId = \str_replace(['\\', '"'], ['\\\\', '\\"'], $accountId);
        $jql = "worklogAuthor = \"{$escapedAccountId}\" AND worklogDate >= '{$startDate}' AND worklogDate <= '{$endDate}'";
        $issues = $this->searchAllIssues($jql);

        $entries = [];
        foreach ($issues as $issue) {
            $fields = ApiValue::object($issue['fields'] ?? null);
            $worklogData = ApiValue::object($fields['worklog'] ?? null);
            $worklogsValue = $worklogData['worklogs'] ?? [];
            $worklogs = \is_array($worklogsValue) ? $worklogsValue : [];
            $issueKey = ApiValue::stringValue($issue['key'] ?? null);

            if (ApiValue::intValue($worklogData['total'] ?? null) > \count($worklogs)) {
                $worklogs = $this->allWorklogs($issueKey);
            }

            foreach ($worklogs as $worklog) {
                $worklog = ApiValue::object($worklog);
                $author = ApiValue::object($worklog['author'] ?? null);
                $date = \substr(ApiValue::stringValue($worklog['started'] ?? null), 0, 10);

                if (ApiValue::stringValue($author['accountId'] ?? null) !== $accountId || $date < $startDate || $date > $endDate) {
                    continue;
                }

                $seconds = ApiValue::intValue($worklog['timeSpentSeconds'] ?? null);
                $project = ApiValue::object($fields['project'] ?? null);
                $comment = ApiValue::object($worklog['comment'] ?? null);
                $entries[] = new JiraWorklogRecord(
                    id: ApiValue::stringValue($worklog['id'] ?? null),
                    date: $date,
                    issue: $issueKey,
                    project: ApiValue::stringValue($project['name'] ?? null),
                    summary: ApiValue::stringValue($fields['summary'] ?? null),
                    seconds: $seconds,
                    hours: \round($seconds / 3600, 2),
                    comment: $this->plainText($comment),
                    timeSpent: ApiValue::stringValue($worklog['timeSpent'] ?? null) ?: $this->formatSeconds($seconds),
                    url: \rtrim($this->siteUrl, '/') . "/browse/{$issueKey}",
                );
            }
        }

        \usort(
            $entries,
            fn(JiraWorklogRecord $a, JiraWorklogRecord $b): int => [$a->date, $a->issue] <=> [$b->date, $b->issue],
        );

        return new JiraWorklogReport(
            entries: $entries,
            accountId: $accountId,
            displayName: ApiValue::stringValue($reportUser['displayName'] ?? null),
            avatarUrl: $this->avatarUrl($reportUser),
            isOwnReport: $accountId === $currentAccountId,
        );
    }

    /** Transitional presentation helper; report domain objects intentionally contain no URLs. */
    public function issueUrl(string $issue): string
    {
        return \rtrim($this->siteUrl, '/') . "/browse/{$issue}";
    }

    /**
     * @param string $path
     * @param array<array-key, mixed>|null $body
     * @param string|null $method
     * @param array<array-key, mixed>|null $query
     * @return array<string, mixed>
     * @throws JsonException
     */
    private function request(string $path, ?array $body = null, ?string $method = null, ?array $query = null): array
    {
        try {
            return $this->transport->request($method ?? 'GET', $path, $body, $query);
        } catch (HttpTransportException $exception) {
            if ($exception->reason === 'unsuccessful_response') {
                if ($exception->status === 429) {
                    throw $this->rateLimitException($exception);
                }
                throw WorkLogRuntimeException::create($this->jiraErrorMessage($exception->status ?? 0, $exception->responseBody));
            }
            if ($exception->reason === 'unexpected_json_payload') {
                throw WorkLogRuntimeException::create($this->translator->trans('jira.invalid_json'));
            }
            throw WorkLogRuntimeException::create(
                $this->translator->trans('jira.connection_failed', ['{message}' => $exception->getMessage()]),
                $exception
            );
        }
    }

    private function rateLimitException(HttpTransportException $exception): RateLimitExceededException
    {
        $retryAfter = $exception->retryAfterSeconds();

        return RateLimitExceededException::withRetryAfter(
            JiraErrorResponse::rateLimitMessage($exception, $this->translator),
            $retryAfter,
            $exception,
        );
    }

    private function jiraErrorMessage(int $status, string $content): string
    {
        $messages = [];

        try {
            $details = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $details = null;
        }

        if (\is_array($details)) {
            $errorMessages = $details['errorMessages'] ?? [];
            foreach (\is_array($errorMessages) ? $errorMessages : [] as $message) {
                if (\is_string($message) && \trim($message) !== '') {
                    $messages[] = \trim($message);
                }
            }

            $fieldErrors = $details['errors'] ?? [];
            foreach (\is_array($fieldErrors) ? $fieldErrors : [] as $field => $message) {
                if (!\is_string($message) || \trim($message) === '') {
                    continue;
                }

                $field = \is_string($field) && $field !== '' ? "{$field}: " : '';
                $messages[] = $field . \trim($message);
            }
        }

        $summary = JiraErrorResponse::summary($status, $this->translator);

        return $messages === [] ? $summary : $summary . ' ' . \implode(' ', $messages);
    }

    /**
     * @param array<string, mixed> $node
     */
    private function plainText(array $node): string
    {
        if (($node['type'] ?? '') === 'hardBreak') {
            return "\n";
        }

        $attrs = $node['attrs'] ?? [];
        $attrs = \is_array($attrs) ? $attrs : [];
        $type = ApiValue::stringValue($node['type'] ?? null);
        $text = ApiValue::stringValue($node['text'] ?? match ($type) {
            'mention', 'emoji', 'status' => $attrs['text'] ?? '',
            'inlineCard' => $attrs['url'] ?? '',
            default => '',
        });

        $content = $node['content'] ?? [];
        $content = \is_array($content) ? $content : [];

        foreach ($content as $child) {
            if (!\is_array($child)) {
                continue;
            }

            $text .= $this->plainText(ApiValue::object($child));
        }

        if (in_array($type, ['paragraph', 'heading', 'listItem'], true)) {
            $text .= "\n";
        }

        return $type === 'doc' ? trim($text) : $text;
    }

    /**
     * @return list<array<string, mixed>>
     * @throws JsonException
     */
    private function searchAllIssues(string $jql): array
    {
        $issues = [];
        $nextPageToken = null;

        do {
            $query = ['jql' => $jql, 'fields' => 'summary,project,worklog', 'maxResults' => 100];
            if ($nextPageToken !== null) {
                $query['nextPageToken'] = $nextPageToken;
            }
            $page = $this->request('/rest/api/3/search/jql', null, 'GET', $query);
            $pageIssues = $page['issues'] ?? [];

            if (\is_array($pageIssues)) {
                foreach ($pageIssues as $issue) {
                    if (\is_array($issue)) {
                        $issues[] = ApiValue::object($issue);
                    }
                }
            }

            $token = $page['nextPageToken'] ?? null;
            $nextPageToken = \is_string($token) ? $token : null;
        } while ($nextPageToken !== null && $nextPageToken !== '');

        return $issues;
    }

    /**
     * @return list<array<string, mixed>>
     * @throws JsonException
     */
    private function allWorklogs(string $issueKey): array
    {
        $worklogs = [];
        $startAt = 0;

        do {
            $page = $this->request("/rest/api/3/issue/{$issueKey}/worklog", null, 'GET', [
                'startAt' => $startAt,
                'maxResults' => 100,
            ]);
            $batchValue = $page['worklogs'] ?? [];
            $batch = [];

            if (\is_array($batchValue)) {
                foreach ($batchValue as $worklog) {
                    if (\is_array($worklog)) {
                        $batch[] = ApiValue::object($worklog);
                    }
                }
            }

            array_push($worklogs, ...$batch);
            $startAt += \count($batch);
            $total = $page['total'] ?? 0;
            $total = \is_int($total) ? $total : 0;
        } while ($batch !== [] && $startAt < $total);

        return $worklogs;
    }

    private function formatSeconds(int $seconds): string
    {
        $hours = \intdiv($seconds, 3600);
        $minutes = \intdiv($seconds % 3600, 60);
        $hoursString = $hours > 0 ? "{$hours}h " : '';
        $minutesString = $minutes > 0 ? "{$minutes}m" : '';

        return \trim($hoursString . $minutesString ?: '0m');
    }

    /** @param array<string, mixed> $user */
    private function avatarUrl(array $user): string
    {
        $avatarUrls = ApiValue::object($user['avatarUrls'] ?? null);

        return ApiValue::stringValue($user['avatarUrl'] ?? $avatarUrls['48x48'] ?? $avatarUrls['32x32'] ?? null);
    }

}
