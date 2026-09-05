<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\TimeTracking\Application\Handler;

use App\Kernel\Exception\ApplicationRuntimeException;
use App\TimeTracking\Application\Command\AddWorklog;
use App\TimeTracking\Application\Command\DeleteWorklog;
use App\TimeTracking\Application\Command\UpdateWorklog;
use App\TimeTracking\Application\Handler\AddWorklogHandler;
use App\TimeTracking\Application\Handler\DeleteWorklogHandler;
use App\TimeTracking\Application\Handler\UpdateWorklogHandler;
use App\TimeTracking\Application\Port\WorklogGateway;
use App\TimeTracking\Domain\Exception\InvalidWorklog;
use App\TimeTracking\Domain\Model\IssueKey;
use App\TimeTracking\Domain\Model\NewWorklog;
use App\TimeTracking\Domain\Model\Worklog;
use App\TimeTracking\Domain\Model\WorklogId;
use App\TimeTracking\Domain\Model\WorkTimeUnits;
use PHPUnit\Framework\TestCase;

final class WorklogHandlersTest extends TestCase
{
    public function testAddHandlerMapsPrimitiveCommandAndOnlyAdds(): void
    {
        $gateway = $this->createMock(WorklogGateway::class);
        $gateway->expects(self::once())->method('add')->with(self::callback(
            static fn(NewWorklog $worklog): bool => $worklog->issue->toString() === 'APP-1'
                && $worklog->date->toString() === '2026-08-31'
                && $worklog->time->inSeconds() === 3600
                && $worklog->comment->toString() === 'Test',
        ));
        $gateway->expects(self::never())->method('update');
        $gateway->expects(self::never())->method('delete');

        $saved = new AddWorklogHandler($gateway, self::timeUnits())->handle(new AddWorklog(' app-1 ', '2026-08-31', '1h', ' Test '));

        self::assertSame(2026, $saved->year);
        self::assertSame(8, $saved->month);
        self::assertSame('APP-1', $saved->issue);
    }

    public function testUpdateHandlerMapsPrimitiveCommandAndOnlyUpdates(): void
    {
        $gateway = $this->createMock(WorklogGateway::class);
        $gateway->expects(self::never())->method('add');
        $gateway->expects(self::once())->method('update')->with(self::callback(
            static fn(Worklog $worklog): bool => $worklog->issue->toString() === 'APP-1'
                && $worklog->id->toString() === '9'
                && $worklog->time->inSeconds() === 7200
                && $worklog->comment->toString() === 'Test',
        ));
        $gateway->expects(self::never())->method('delete');

        $saved = new UpdateWorklogHandler($gateway, self::timeUnits())->handle(new UpdateWorklog('APP-1', '2026-08-31', '9', '2h', 'Test'));

        self::assertSame(2026, $saved->year);
        self::assertSame(8, $saved->month);
        self::assertSame('APP-1', $saved->issue);
    }

    public function testDeleteHandlerMapsPrimitiveCommandAndOnlyDeletes(): void
    {
        $gateway = $this->createMock(WorklogGateway::class);
        $gateway->expects(self::never())->method('add');
        $gateway->expects(self::never())->method('update');
        $gateway->expects(self::once())->method('delete')->with(
            self::callback(static fn(IssueKey $issue): bool => $issue->toString() === 'APP-1'),
            self::callback(static fn(WorklogId $id): bool => $id->toString() === '9'),
        );

        $saved = new DeleteWorklogHandler($gateway)->handle(new DeleteWorklog('APP-1', '2026-08-31', '9'));

        self::assertSame(2026, $saved->year);
        self::assertSame(8, $saved->month);
        self::assertSame('APP-1', $saved->issue);
    }

    public function testInvalidDomainInputIsMappedWithoutCallingGatewayOrChangingMessage(): void
    {
        $gateway = $this->createMock(WorklogGateway::class);
        $gateway->expects(self::never())->method('add');

        try {
            new AddWorklogHandler($gateway, self::timeUnits())->handle(new AddWorklog('invalid', '2026-08-31', '1h', ''));
            self::fail('Expected invalid worklog input.');
        } catch (ApplicationRuntimeException $exception) {
            self::assertSame('Nieprawidłowy numer zadania Jiry.', $exception->getMessage());
            self::assertInstanceOf(InvalidWorklog::class, $exception->getPrevious());
        }
    }

    private static function timeUnits(): WorkTimeUnits
    {
        return new WorkTimeUnits(28800, 144000);
    }
}
