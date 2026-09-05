<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Exception;

final class ReportExportConfigException extends ApplicationRuntimeException
{
    public static function missing(string $name): self
    {
        return new self("Brak zmiennej {$name} w pliku .env.");
    }

    public static function invalid(string $name, string $expected, ?\Throwable $previous = null): self
    {
        return new self("Nieprawidłowa wartość {$name}. Oczekiwano: {$expected}.", 0, $previous);
    }
}
