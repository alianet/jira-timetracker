<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Reporting\Presentation\Http;

use App\Reporting\Presentation\Http\ReportQuery;
use Safe\DateTimeImmutable;
use Tests\TestCase;

final class ReportQueryTest extends TestCase
{
    public function testItClampsYearAndMonthFromQuery(): void
    {
        $period = ReportQuery::fromQuery([
            'year' => '1999',
            'month' => '20',
        ]);

        self::assertSame(2000, $period->year);
        self::assertSame(12, $period->month);
        self::assertInstanceOf(DateTimeImmutable::class, $period->now);
    }

    public function testItRecognizesCurrentPeriod(): void
    {
        $now = new DateTimeImmutable();
        $period = ReportQuery::fromQuery([
            'year' => $now->format('Y'),
            'month' => $now->format('n'),
        ]);

        self::assertTrue($period->isCurrent());
    }
}
