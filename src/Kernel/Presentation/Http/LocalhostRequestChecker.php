<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Presentation\Http;

use function Safe\parse_url;

final readonly class LocalhostRequestChecker
{
    /** @param array<string, mixed> $server */
    public function isLocalhost(array $server): bool
    {
        $authority = $server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? null;
        if (!is_string($authority) || $authority === '') {
            return false;
        }

        try {
            $uri = parse_url('http://' . $authority);
        } catch (\Throwable) {
            return false;
        }

        if (isset($uri['user'])
            || isset($uri['pass'])
            || isset($uri['path'])
            || isset($uri['query'])
            || isset($uri['fragment'])
            || (($uri['port'] ?? 1) < 1)
        ) {
            return false;
        }

        $host = $uri['host'] ?? null;
        if (!is_string($host)) {
            return false;
        }

        $host = strtolower(rtrim($host, '.'));
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }
        if ($host === 'localhost' || $host === '::1') {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && str_starts_with($host, '127.');
    }
}
