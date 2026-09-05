<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Application\Authentication;

enum AuthenticationMode
{
    /** Three-legged OAuth: the user grants access in the browser. */
    case InteractiveOAuth;

    /** Non-interactive personal API token configured up front. */
    case PersonalToken;
}
