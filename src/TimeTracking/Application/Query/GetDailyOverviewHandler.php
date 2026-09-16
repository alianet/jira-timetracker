<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Application\Query;

final readonly class GetDailyOverviewHandler
{
    public function __construct(private DailyOverviewDirectory $directory) {}

    public function handle(): DailyOverview
    {
        return $this->directory->get();
    }
}
