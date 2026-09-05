<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Infrastructure\Session;

use App\Identity\Presentation\Http\SessionSecurity;

use function Safe\session_regenerate_id;

final readonly class NativeSessionSecurity implements SessionSecurity
{
    public function randomToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function regenerateId(): void
    {
        session_regenerate_id(true);
    }
}
