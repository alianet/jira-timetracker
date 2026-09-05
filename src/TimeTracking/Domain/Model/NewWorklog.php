<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

final readonly class NewWorklog
{
    public function __construct(
        public IssueKey $issue,
        public WorkDate $date,
        public TimeAmount $time,
        public WorklogComment $comment,
    ) {}
}
