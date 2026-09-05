<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Domain\Port;

interface HolidayCalendar
{
    /** @return list<string> */
    public function holidaysOn(\DateTimeImmutable $date): array;

    public function isDayOff(\DateTimeImmutable $date): bool;
}
