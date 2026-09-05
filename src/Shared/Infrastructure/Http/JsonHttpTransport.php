<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

interface JsonHttpTransport
{
    /**
     * @param array<array-key, mixed>|null $body
     * @param array<array-key, mixed>|null $query
     * @return array<string, mixed>
     * @throws HttpTransportException
     * @throws \JsonException
     */
    public function request(string $method, string $path, ?array $body = null, ?array $query = null): array;
}
