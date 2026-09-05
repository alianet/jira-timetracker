<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Application\Handler;

use App\Kernel\Exception\ApplicationRuntimeException;
use App\TimeTracking\Application\Command\DeleteWorklog;
use App\TimeTracking\Application\Port\WorklogGateway;
use App\TimeTracking\Application\SavedWorklog;
use App\TimeTracking\Domain\Exception\InvalidWorklog;
use App\TimeTracking\Domain\Model\IssueKey;
use App\TimeTracking\Domain\Model\WorkDate;
use App\TimeTracking\Domain\Model\WorklogId;

final readonly class DeleteWorklogHandler
{
    public function __construct(private WorklogGateway $gateway) {}

    public function handle(DeleteWorklog $command): SavedWorklog
    {
        try {
            $issue = IssueKey::fromString($command->issue);
            $date = WorkDate::fromString($command->date);
            $this->gateway->delete($issue, WorklogId::fromString($command->id));

            return new SavedWorklog($date->year(), $date->month(), $issue->toString());
        } catch (InvalidWorklog $exception) {
            throw ApplicationRuntimeException::create($exception->getMessage(), $exception);
        }
    }
}
