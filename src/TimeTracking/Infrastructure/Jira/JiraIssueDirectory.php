<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Infrastructure\Jira;

use App\Kernel\Support\ApiValue;
use App\TimeTracking\Application\Query\IssueDirectory;
use App\TimeTracking\Application\Query\IssueSearchPhrase;
use App\TimeTracking\Application\Query\IssueSearchResult;
use App\TimeTracking\Domain\Model\IssueKey;

final readonly class JiraIssueDirectory implements IssueDirectory
{
    public function __construct(
        private JiraTransport $transport,
        private string $siteUrl,
    ) {}

    public function search(IssueSearchPhrase $phrase, int $limit): array
    {
        $payload = $this->transport->request('GET', '/rest/api/3/issue/picker', null, [
            'query' => $phrase->value,
            'showSubTasks' => 'true',
        ]);
        $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];
        $issues = [];

        foreach ($sections as $section) {
            if (!is_array($section) || !is_array($section['issues'] ?? null)) {
                continue;
            }

            foreach ($section['issues'] as $issue) {
                if (!is_array($issue)) {
                    continue;
                }
                $key = ApiValue::stringValue($issue['key'] ?? null);
                if ($key === '' || isset($issues[$key])) {
                    continue;
                }

                $issues[$key] = new IssueSearchResult(
                    IssueKey::fromString($key),
                    ApiValue::stringValue($issue['summaryText'] ?? $issue['summary'] ?? null),
                    rtrim($this->siteUrl, '/') . '/browse/' . $key,
                );
                if (count($issues) === $limit) {
                    break 2;
                }
            }
        }

        return array_values($issues);
    }
}
