<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Reporting\Domain;

use App\Reporting\Domain\IssueKey;
use App\Reporting\Domain\ReportTimeZone;
use App\Reporting\Domain\WorklogAuthorId;
use App\Reporting\Domain\WorklogDate;
use App\Reporting\Domain\WorklogDuration;
use PHPUnit\Framework\TestCase;

final class ReportingValueObjectsTest extends TestCase
{
    public function testWorklogAuthorIdAcceptsOpaqueAtlassianIdentifier(): void
    {
        self::assertSame('557058:abc_def-123', WorklogAuthorId::fromString(' 557058:abc_def-123 ')->toString());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidWorklogAuthorIds')]
    public function testWorklogAuthorIdRejectsInvalidInput(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid worklog author identifier.');

        WorklogAuthorId::fromString($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidWorklogAuthorIds(): iterable
    {
        yield 'empty' => ['   '];
        yield 'quote requiring JQL escaping' => ['account" OR assignee IS NOT EMPTY'];
        yield 'backslash requiring JQL escaping' => ['account\\admin'];
        yield 'space' => ['account admin'];
    }

    public function testWorklogDateExposesCalendarDayWithoutStringParsingByItsConsumer(): void
    {
        $date = WorklogDate::fromString('2026-04-09', ReportTimeZone::fromName('Pacific/Auckland'));

        self::assertSame('2026-04-09', $date->toString());
        self::assertSame(9, $date->day());
    }

    public function testInvalidWorklogDateKeepsExistingError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid worklog date.');

        WorklogDate::fromString('2026-02-30', ReportTimeZone::fromName('Pacific/Auckland'));
    }

    public function testDurationRejectsNegativeSecondsWithExistingError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Worklog duration cannot be negative.');

        WorklogDuration::fromSeconds(-1);
    }

    public function testIssueKeyPreservesProviderValue(): void
    {
        self::assertSame('APP-10', IssueKey::fromString('APP-10')->toString());
    }
}
