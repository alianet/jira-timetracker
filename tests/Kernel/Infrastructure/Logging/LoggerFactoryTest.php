<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Kernel\Infrastructure\Logging;

use App\Kernel\Infrastructure\Logging\LoggerFactory;
use PHPUnit\Framework\TestCase;

final class LoggerFactoryTest extends TestCase
{
    public function testWritesExceptionsAtConfiguredMinimumLevelWithStackTrace(): void
    {
        $logPath = sys_get_temp_dir() . '/jira-timetracker-log-' . bin2hex(random_bytes(8)) . '/app.log';
        $logger = new LoggerFactory()->create($logPath, 'warning');

        $logger->info('This entry should be filtered out.');
        $logger->error('Request failed.', ['exception' => new \RuntimeException('Sensitive failure')]);

        $contents = (string) file_get_contents($logPath);
        self::assertStringNotContainsString('filtered out', $contents);
        self::assertStringContainsString('Request failed.', $contents);
        self::assertStringContainsString('RuntimeException', $contents);
        self::assertStringContainsString('Sensitive failure', $contents);
        self::assertStringContainsString('#0', $contents);
    }
}
