<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Infrastructure\Jira;

use App\TimeTracking\Application\Port\WorklogGateway;
use App\TimeTracking\Domain\Model\IssueKey;
use App\TimeTracking\Domain\Model\NewWorklog;
use App\TimeTracking\Domain\Model\Worklog;
use App\TimeTracking\Domain\Model\WorklogId;
use Safe\DateTimeImmutable;

final readonly class JiraWorklogRepository implements WorklogGateway
{
    public function __construct(
        private JiraTransport $transport,
        private \DateTimeZone $timezone,
    ) {}

    public function add(NewWorklog $worklog): void
    {
        $payload = [
            'timeSpent' => JiraTimeFormat::formatSeconds($worklog->time->inSeconds()),
            'started' => new DateTimeImmutable($worklog->date->toString() . ' 09:00:00', $this->timezone)->format('Y-m-d\TH:i:s.000O'),
        ];
        if ($worklog->comment->toString() !== '') {
            $payload['comment'] = self::comment($worklog->comment->toString());
        }

        $this->transport->request('POST', '/rest/api/3/issue/' . $worklog->issue->toString() . '/worklog', $payload);
    }

    public function update(Worklog $worklog): void
    {
        $this->transport->request(
            'PUT',
            '/rest/api/3/issue/' . $worklog->issue->toString() . '/worklog/' . $worklog->id->toString(),
            [
                'timeSpent' => JiraTimeFormat::formatSeconds($worklog->time->inSeconds()),
                'comment' => self::comment($worklog->comment->toString()),
            ],
        );
    }

    public function delete(IssueKey $issue, WorklogId $id): void
    {
        $this->transport->request('DELETE', '/rest/api/3/issue/' . $issue->toString() . '/worklog/' . $id->toString());
    }

    /** @return array<string, mixed> */
    private static function comment(string $comment): array
    {
        return [
            'type' => 'doc',
            'version' => 1,
            'content' => $comment === '' ? [] : [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => $comment]],
            ]],
        ];
    }

}
