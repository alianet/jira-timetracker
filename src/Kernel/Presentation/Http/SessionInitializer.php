<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Presentation\Http;

use function Safe\session_start;

final class SessionInitializer
{
    /** @param array<string, mixed> $server */
    public function start(array $server): void
    {
        $secureCookie = !empty($server['HTTPS']) && $server['HTTPS'] !== 'off';

        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $secureCookie,
        ]);
        session_start();

        $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    }
}
