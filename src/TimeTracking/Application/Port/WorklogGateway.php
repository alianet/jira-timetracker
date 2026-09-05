<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Application\Port;

use App\TimeTracking\Domain\Model\IssueKey;
use App\TimeTracking\Domain\Model\NewWorklog;
use App\TimeTracking\Domain\Model\Worklog;
use App\TimeTracking\Domain\Model\WorklogId;

interface WorklogGateway
{
    public function add(NewWorklog $worklog): void;

    public function update(Worklog $worklog): void;

    public function delete(IssueKey $issue, WorklogId $id): void;
}
