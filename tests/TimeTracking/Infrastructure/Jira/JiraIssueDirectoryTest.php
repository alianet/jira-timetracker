<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\TimeTracking\Infrastructure\Jira;

use App\TimeTracking\Application\Query\IssueSearchPhrase;
use App\TimeTracking\Infrastructure\Jira\JiraIssueDirectory;
use App\TimeTracking\Infrastructure\Jira\JiraTransport;
use PHPUnit\Framework\TestCase;

final class JiraIssueDirectoryTest extends TestCase
{
    public function testMapsAtlassianFieldsDeduplicatesAndLimitsIssues(): void
    {
        $transport = new IssuePickerTransport(['sections' => [
            ['issues' => [
                ['key' => 'APP-1', 'summaryText' => 'First'],
                ['key' => 'APP-1', 'summaryText' => 'Duplicate'],
                ['key' => 'APP-2', 'summary' => 'Fallback'],
                ['summaryText' => 'Missing key'],
            ]],
        ]]);
        $result = new JiraIssueDirectory($transport, 'https://site.test/')->search(
            IssueSearchPhrase::fromString('app'),
            2,
        );

        self::assertSame(['APP-1', 'APP-2'], array_map(static fn($issue) => $issue->key->toString(), $result));
        self::assertSame(['First', 'Fallback'], array_map(static fn($issue) => $issue->summary, $result));
        self::assertSame('https://site.test/browse/APP-1', $result[0]->url);
        self::assertSame([['GET', '/rest/api/3/issue/picker', null, [
            'query' => 'app', 'showSubTasks' => 'true',
        ]]], $transport->requests);
    }
}

final class IssuePickerTransport implements JiraTransport
{
    /** @var list<array{string, string, array<array-key, mixed>|null, array<array-key, mixed>|null}> */
    public array $requests = [];

    /** @param array<string, mixed> $response */
    public function __construct(private array $response) {}

    public function request(string $method, string $path, ?array $body = null, ?array $query = null): array
    {
        $this->requests[] = [$method, $path, $body, $query];

        return $this->response;
    }
}
