<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Domain;

final readonly class WorklogEntry
{
    public function __construct(
        public string $id,
        public WorklogDate $date,
        public IssueKey $issue,
        public string $project,
        public string $summary,
        public WorklogDuration $duration,
        public string $comment,
    ) {}
}
