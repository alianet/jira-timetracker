<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Domain\Model;

use App\TimeTracking\Domain\Exception\InvalidWorklog;

final readonly class WorkDate
{
    private function __construct(private \DateTimeImmutable $value) {}

    public static function fromString(string $value): self
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
            throw new InvalidWorklog('Nieprawidłowa data wpisu czasu.');
        }

        return new self($date);
    }

    public function toString(): string
    {
        return $this->value->format('Y-m-d');
    }

    public function year(): int
    {
        return (int) $this->value->format('Y');
    }

    public function month(): int
    {
        return (int) $this->value->format('n');
    }
}
