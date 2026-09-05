<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Infrastructure\Jira;

interface JiraTransport
{
    /**
     * @param array<array-key, mixed>|null $body
     * @param array<array-key, mixed>|null $query
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, ?array $body = null, ?array $query = null): array;
}
