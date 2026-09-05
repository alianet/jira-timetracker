<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Infrastructure\Jira;

final readonly class JiraWorklogRecord
{
    public function __construct(
        public string $id,
        public string $date,
        public string $issue,
        public string $project,
        public string $summary,
        public int $seconds,
        public float $hours,
        public string $comment,
        public string $timeSpent,
        public string $url,
    ) {}
}
