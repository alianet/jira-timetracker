<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Exception;

class ApplicationRuntimeException extends \RuntimeException
{
    public static function create(string $message, ?\Throwable $previous = null): self
    {
        return new self($message, 0, $previous);
    }
}
