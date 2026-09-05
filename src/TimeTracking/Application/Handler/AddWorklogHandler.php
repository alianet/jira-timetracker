<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Application\Handler;

use App\Kernel\Exception\ApplicationRuntimeException;
use App\TimeTracking\Application\Command\AddWorklog;
use App\TimeTracking\Application\Port\WorklogGateway;
use App\TimeTracking\Application\SavedWorklog;
use App\TimeTracking\Domain\Exception\InvalidWorklog;
use App\TimeTracking\Domain\Model\IssueKey;
use App\TimeTracking\Domain\Model\NewWorklog;
use App\TimeTracking\Domain\Model\TimeAmount;
use App\TimeTracking\Domain\Model\WorkDate;
use App\TimeTracking\Domain\Model\WorklogComment;
use App\TimeTracking\Domain\Model\WorkTimeUnits;

final readonly class AddWorklogHandler
{
    public function __construct(
        private WorklogGateway $gateway,
        private WorkTimeUnits $timeUnits,
    ) {}

    public function handle(AddWorklog $command): SavedWorklog
    {
        try {
            $issue = IssueKey::fromString($command->issue);
            $date = WorkDate::fromString($command->date);
            $this->gateway->add(new NewWorklog(
                $issue,
                $date,
                TimeAmount::fromString($command->timeSpent, $this->timeUnits),
                WorklogComment::fromString($command->comment),
            ));

            return new SavedWorklog($date->year(), $date->month(), $issue->toString());
        } catch (InvalidWorklog $exception) {
            throw ApplicationRuntimeException::create($exception->getMessage(), $exception);
        }
    }
}
