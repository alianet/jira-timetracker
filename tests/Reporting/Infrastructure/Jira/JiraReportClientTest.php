<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Reporting\Infrastructure\Jira;

use App\Kernel\Exception\ApplicationRuntimeException as WorkLogRuntimeException;
use App\Kernel\Infrastructure\Translation\TranslatorFactory;
use App\Reporting\Domain\WorklogAuthorId;
use App\Reporting\Infrastructure\Jira\JiraReportClient;
use App\Shared\Infrastructure\Http\SymfonyJsonHttpTransport;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tests\TestCase;

final class JiraReportClientTest extends TestCase
{
    public function testPreservesReportErrorMapping(): void
    {
        $client = $this->client(new MockHttpClient(new MockResponse(
            '{"errorMessages":["Raport niedostępny."],"errors":{"jql":"Błędne zapytanie."}}',
            ['http_code' => 400],
        )));

        $this->expectException(WorkLogRuntimeException::class);
        $this->expectExceptionMessage('Jira odrzuciła przesłane dane. Raport niedostępny. jql: Błędne zapytanie.');
        $client->worklogReport(2024, 2);
    }

    public function testMapsAndFiltersAWorklogReportWithoutNetworkAccess(): void
    {
        $http = new MockHttpClient([
            new MockResponse('{"accountId":"current","displayName":"Current User"}'),
            new MockResponse('{"accountId":"selected","displayName":"Selected User","avatarUrls":{"48x48":"https://avatar.test/user.png"}}'),
            new MockResponse(json_encode([
                'issues' => [[
                    'key' => 'APP-12',
                    'fields' => [
                        'summary' => 'Mapped summary',
                        'project' => ['name' => 'Mapped project'],
                        'worklog' => [
                            'total' => 3,
                            'worklogs' => [
                                $this->worklog('7', 'selected', '2024-02-29', 3660, ''),
                                $this->worklog('8', 'other', '2024-02-20', 7200, '2h'),
                                $this->worklog('9', 'selected', '2024-03-01', 1800, '30m'),
                            ],
                        ],
                    ],
                ]],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $report = $this->client($http)->worklogReport(2024, 2, WorklogAuthorId::fromString(' selected '));

        self::assertSame('selected', $report->accountId);
        self::assertSame('Selected User', $report->displayName);
        self::assertSame('https://avatar.test/user.png', $report->avatarUrl);
        self::assertFalse($report->isOwnReport);
        self::assertCount(1, $report->entries);
        $entry = $report->entries[0];
        self::assertSame('APP-12', $entry->issue);
        self::assertSame('Mapped project', $entry->project);
        self::assertSame('Mapped summary', $entry->summary);
        self::assertSame('1h 1m', $entry->timeSpent);
        self::assertSame("Text @Person\nNext", $entry->comment);
        self::assertSame('https://example.atlassian.net/browse/APP-12', $entry->url);
    }

    public function testPreservesMissingOptionalJiraFieldsAsEmptyValues(): void
    {
        $report = $this->client(new MockHttpClient([
            new MockResponse('{"accountId":"current"}'),
            new MockResponse('{"issues":[]}'),
        ]))->worklogReport(2024, 2);

        self::assertSame('current', $report->accountId);
        self::assertSame('', $report->displayName);
        self::assertSame('', $report->avatarUrl);
        self::assertTrue($report->isOwnReport);
        self::assertSame([], $report->entries);
    }

    /** @return array<string, mixed> */
    private function worklog(string $id, string $author, string $date, int $seconds, string $timeSpent): array
    {
        return [
            'id' => $id,
            'author' => ['accountId' => $author],
            'started' => "{$date}T09:00:00.000+0100",
            'timeSpentSeconds' => $seconds,
            'timeSpent' => $timeSpent,
            'comment' => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Text '],
                        ['type' => 'mention', 'attrs' => ['text' => '@Person']],
                        ['type' => 'hardBreak'],
                        ['type' => 'text', 'text' => 'Next'],
                    ],
                ]],
            ],
        ];
    }

    private function client(MockHttpClient $http): JiraReportClient
    {
        return new JiraReportClient(
            'https://example.atlassian.net',
            new SymfonyJsonHttpTransport('https://api.atlassian.test', $http),
            $this->translator(),
        );
    }

    private function translator(): \Symfony\Contracts\Translation\TranslatorInterface
    {
        return new TranslatorFactory()->create(dirname(__DIR__, 4) . '/translations', ['pl', 'en'], 'pl');
    }
}
