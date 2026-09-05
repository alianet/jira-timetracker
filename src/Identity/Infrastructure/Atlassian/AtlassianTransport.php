<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Infrastructure\Atlassian;

interface AtlassianTransport
{
    /**
     * @param array<array-key, mixed>|null $query
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, ?array $query = null): array;
}
