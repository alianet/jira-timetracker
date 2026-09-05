<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Config;

use App\Kernel\Exception\ApplicationConfigException;
use Symfony\Component\Dotenv\Dotenv;

use function Safe\file_get_contents;

final readonly class Config
{
    /** @param array<string, string> $values */
    private function __construct(private array $values) {}

    public static function fromDirectory(string $directory): self
    {
        $envPath = rtrim($directory, '/') . '/.env';
        $localPath = $envPath . '.local';
        $contents = self::read($envPath);

        if (is_file($localPath)) {
            $contents .= "\n" . self::read($localPath);
        }

        try {
            $values = new Dotenv()->parse($contents, $envPath . '[.local]');
        } catch (\Throwable $exception) {
            throw ApplicationConfigException::invalid($envPath, $exception);
        }

        return new self($values);
    }

    public function required(string $name): string
    {
        return $this->nullable($name) ?? throw ApplicationConfigException::missing($name);
    }

    public function nullable(string $name): ?string
    {
        $value = $this->values[$name] ?? null;

        return $value === null || trim($value) === '' ? null : $value;
    }

    private static function read(string $path): string
    {
        try {
            return file_get_contents($path);
        } catch (\Throwable $exception) {
            throw ApplicationConfigException::unreadable($path, $exception);
        }
    }

    public static function optional(string $name): ?string
    {
        $value = getenv($name);

        if ($value === false || trim($value) === '') {
            return null;
        }

        return $value;
    }

    /**
     * @param list<string> $allowedValues
     */
    public function choice(string $name, array $allowedValues, string $default): string
    {
        $value = strtolower(trim($this->nullable($name) ?? $default));

        if (in_array($value, $allowedValues, true)) {
            return $value;
        }

        $allowed = implode(', ', $allowedValues);
        throw new \RuntimeException("Nieprawidłowa wartość {$name}. Dozwolone: {$allowed}.");
    }
}
