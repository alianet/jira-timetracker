<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\TimeTracking\Domain\Model;

use App\TimeTracking\Domain\Exception\InvalidWorklog;
use App\TimeTracking\Domain\Model\IssueKey;
use App\TimeTracking\Domain\Model\TimeAmount;
use App\TimeTracking\Domain\Model\WorkDate;
use App\TimeTracking\Domain\Model\WorklogComment;
use App\TimeTracking\Domain\Model\WorklogId;
use App\TimeTracking\Domain\Model\WorkTimeUnits;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    public function testIssueKeyIsNormalized(): void
    {
        self::assertSame('APP_2-17', IssueKey::fromString(' app_2-17 ')->toString());
    }

    public function testWorklogIdIsTrimmed(): void
    {
        self::assertSame('123', WorklogId::fromString(' 123 ')->toString());
    }

    public function testDateExposesCanonicalValueAndRedirectParts(): void
    {
        $date = WorkDate::fromString('2026-08-31');

        self::assertSame('2026-08-31', $date->toString());
        self::assertSame(2026, $date->year());
        self::assertSame(8, $date->month());
    }

    public function testCommentIsTrimmedAndMayBeEmpty(): void
    {
        self::assertSame('Analiza', WorklogComment::fromString('  Analiza  ')->toString());
        self::assertSame('', WorklogComment::fromString('  ')->toString());
    }

    /** @return iterable<string, array{string, int}> */
    public static function validTimes(): iterable
    {
        yield 'decimal comma' => ['1,5h', 5400];
        yield 'decimal point' => ['2.25 h', 8100];
        yield 'decimal rounding' => ['0.999h', 3600];
        yield 'compact Jira' => ['1w2d3h4m', 212640];
        yield 'spaced Jira' => [' 1h  30m ', 5400];
        yield 'zero' => ['0h', 0];
    }

    #[DataProvider('validTimes')]
    public function testTimeUsesCanonicalSeconds(string $input, int $seconds): void
    {
        $amount = TimeAmount::fromString($input, new WorkTimeUnits(28800, 144000));

        self::assertSame($seconds, $amount->inSeconds());
    }

    /** @return iterable<string, array{callable(): object, string}> */
    public static function invalidValues(): iterable
    {
        yield 'issue' => [static fn(): IssueKey => IssueKey::fromString('1-invalid'), 'Nieprawidłowy numer zadania Jiry.'];
        yield 'id empty' => [static fn(): WorklogId => WorklogId::fromString(''), 'Nieprawidłowy identyfikator wpisu czasu.'];
        yield 'id non numeric' => [static fn(): WorklogId => WorklogId::fromString('12x'), 'Nieprawidłowy identyfikator wpisu czasu.'];
        yield 'date' => [static fn(): WorkDate => WorkDate::fromString('2026-02-30'), 'Nieprawidłowa data wpisu czasu.'];
        yield 'time' => [static fn(): TimeAmount => TimeAmount::fromString('90 minutes', new WorkTimeUnits(28800, 144000)), 'Podaj czas w formacie Jiry, np. 1h 30m.'];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValuesUseDomainException(callable $factory, string $message): void
    {
        $this->expectException(InvalidWorklog::class);
        $this->expectExceptionMessage($message);
        $factory();
    }

    public function testTimeUsesInjectedDayAndWeekLengths(): void
    {
        $amount = TimeAmount::fromString('1w 1d', new WorkTimeUnits(21600, 86400));

        self::assertSame(108000, $amount->inSeconds());
    }
}
