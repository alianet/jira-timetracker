<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Presentation\ViewModel;

final readonly class ReportPageContext
{
    public function __construct(
        public string $siteName,
        public string $siteUrl,
        public string $csrfToken,
        public bool $exportEnabled,
    ) {}
}
