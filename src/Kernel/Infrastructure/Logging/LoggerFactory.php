<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Infrastructure\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final readonly class LoggerFactory
{
    public function create(string $logPath, string $minimumLevel): LoggerInterface
    {
        $handler = new StreamHandler($logPath, $this->level($minimumLevel));
        $handler->setFormatter(new LineFormatter(
            allowInlineLineBreaks: true,
            ignoreEmptyContextAndExtra: true,
            includeStacktraces: true,
        ));

        return new Logger('application', [$handler]);
    }

    private function level(string $name): Level
    {
        return match ($name) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'warning' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => throw new \InvalidArgumentException("Unsupported log level: {$name}."),
        };
    }
}
