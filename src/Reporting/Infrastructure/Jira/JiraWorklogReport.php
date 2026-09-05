<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Infrastructure\Jira;

final readonly class JiraWorklogReport
{
    /** @param list<JiraWorklogRecord> $entries */
    public function __construct(
        public array $entries,
        public string $accountId,
        public string $displayName,
        public string $avatarUrl,
        public bool $isOwnReport,
    ) {}
}
