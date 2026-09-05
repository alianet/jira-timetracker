<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Support;

final class ApiValue
{
    /** @return array<string, mixed> */
    public static function object(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    public static function stringValue(mixed $value): string
    {
        return \is_string($value) || \is_int($value) ? (string) $value : '';
    }

    public static function intValue(mixed $value): int
    {
        return \is_int($value) || \is_numeric($value) ? (int) $value : 0;
    }

    /** @return array<string, string> */
    public static function stringMap(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $key => $item) {
            if (\is_string($key) && (\is_string($item) || \is_int($item))) {
                $strings[$key] = (string) $item;
            }
        }

        return $strings;
    }
}
