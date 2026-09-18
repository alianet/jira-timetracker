<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Application\Query;

final readonly class DailySprint
{
    public function __construct(
        public int $id,
        public string $name,
        public string $state,
        public ?int $originBoardId,
        public bool $primary,
        public ?string $startDate,
        public ?string $endDate,
    ) {}
}
