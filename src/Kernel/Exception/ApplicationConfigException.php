<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Exception;

final class ApplicationConfigException extends ApplicationRuntimeException
{
    public static function unreadable(string $path, ?\Throwable $previous = null): self
    {
        return new self("Nie udało się odczytać pliku konfiguracji {$path}.", 0, $previous);
    }

    public static function invalid(string $path, ?\Throwable $previous = null): self
    {
        return new self("Plik konfiguracji {$path} ma nieprawidłowy format.", 0, $previous);
    }

    public static function invalidValue(string $name, string $expected, ?\Throwable $previous = null): self
    {
        return new self("Nieprawidłowa wartość {$name}. Oczekiwano: {$expected}.", 0, $previous);
    }

    public static function missing(string $name): self
    {
        return new self("Brak zmiennej {$name} w pliku .env lub .env.local.");
    }
}
