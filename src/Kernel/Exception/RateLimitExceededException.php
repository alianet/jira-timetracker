<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Exception;

final class RateLimitExceededException extends ApplicationRuntimeException
{
    private function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function withRetryAfter(string $message, ?int $retryAfterSeconds = null, ?\Throwable $previous = null): self
    {
        return new self($message, $retryAfterSeconds, $previous);
    }
}
