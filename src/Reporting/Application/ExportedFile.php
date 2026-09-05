<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Application;

final readonly class ExportedFile
{
    public function __construct(
        public string $content,
        public string $filename,
    ) {}
}
