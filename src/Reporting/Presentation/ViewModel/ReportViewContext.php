<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Presentation\ViewModel;

final readonly class ReportViewContext
{
    /**
     * @param list<int> $years
     * @param list<string> $worklogTags
     */
    public function __construct(
        public string $accountId,
        public string $displayName,
        public string $avatarUrl,
        public bool $isOwnReport,
        public string $defaultWorklogDate,
        public string $siteName,
        public string $siteUrl,
        public string $csrfToken,
        public bool $exportEnabled,
        public bool $saved,
        public int $currentYear,
        public int $currentMonth,
        public array $years,
        public array $worklogTags,
    ) {}
}
