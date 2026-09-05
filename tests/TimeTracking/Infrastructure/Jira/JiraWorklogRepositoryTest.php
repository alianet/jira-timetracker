<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\TimeTracking\Infrastructure\Jira;

use App\TimeTracking\Domain\Model\IssueKey;
use App\TimeTracking\Domain\Model\NewWorklog;
use App\TimeTracking\Domain\Model\TimeAmount;
use App\TimeTracking\Domain\Model\WorkDate;
use App\TimeTracking\Domain\Model\Worklog;
use App\TimeTracking\Domain\Model\WorklogComment;
use App\TimeTracking\Domain\Model\WorklogId;
use App\TimeTracking\Infrastructure\Jira\JiraTimeFormat;
use App\TimeTracking\Infrastructure\Jira\JiraTransport;
use App\TimeTracking\Infrastructure\Jira\JiraWorklogRepository;
use PHPUnit\Framework\TestCase;

final class JiraWorklogRepositoryTest extends TestCase
{
    public function testMapsAddToJiraRequestIncludingDocumentComment(): void
    {
        $transport = new RecordingJiraTransport();
        $repository = new JiraWorklogRepository($transport, new \DateTimeZone('America/New_York'));
        $repository->add(new NewWorklog(
            IssueKey::fromString('APP-12'),
            WorkDate::fromString('2026-08-31'),
            TimeAmount::fromString('1.5h', JiraTimeFormat::units()),
            WorklogComment::fromString('Mapped comment'),
        ));

        self::assertSame([['POST', '/rest/api/3/issue/APP-12/worklog', [
            'timeSpent' => '1h 30m',
            'started' => '2026-08-31T09:00:00.000-0400',
            'comment' => [
                'type' => 'doc',
                'version' => 1,
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Mapped comment']],
                ]],
            ],
        ]]], $transport->requests);
    }

    public function testPreservesEmptyCommentSemanticsForAddAndUpdate(): void
    {
        $transport = new RecordingJiraTransport();
        $repository = new JiraWorklogRepository($transport, new \DateTimeZone('Europe/Warsaw'));
        $repository->add(new NewWorklog(
            IssueKey::fromString('APP-1'),
            WorkDate::fromString('2026-01-02'),
            TimeAmount::fromString('2h', JiraTimeFormat::units()),
            WorklogComment::fromString(''),
        ));
        $repository->update(new Worklog(
            IssueKey::fromString('APP-1'),
            WorklogId::fromString('9'),
            TimeAmount::fromString('3h', JiraTimeFormat::units()),
            WorklogComment::fromString(''),
        ));

        self::assertArrayNotHasKey('comment', $transport->requests[0][2] ?? []);
        self::assertSame(['type' => 'doc', 'version' => 1, 'content' => []], $transport->requests[1][2]['comment'] ?? null);
    }

    public function testMapsDeleteWithoutPayload(): void
    {
        $transport = new RecordingJiraTransport();
        $repository = new JiraWorklogRepository($transport, new \DateTimeZone('Europe/Warsaw'));
        $repository->delete(IssueKey::fromString('APP-1'), WorklogId::fromString('9'));

        self::assertSame([['DELETE', '/rest/api/3/issue/APP-1/worklog/9', null]], $transport->requests);
    }

    public function testJiraDayAndWeekRemainProtocolConstants(): void
    {
        $units = JiraTimeFormat::units();

        self::assertSame(28800, $units->secondsPerDay);
        self::assertSame(144000, $units->secondsPerWeek);
        self::assertSame('1w 1d', JiraTimeFormat::formatSeconds(172800));
    }
}

final class RecordingJiraTransport implements JiraTransport
{
    /** @var list<array{string, string, array<array-key, mixed>|null}> */
    public array $requests = [];

    public function request(string $method, string $path, ?array $body = null, ?array $query = null): array
    {
        $this->requests[] = [$method, $path, $body];

        return [];
    }
}
