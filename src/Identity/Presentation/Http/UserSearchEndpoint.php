<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Presentation\Http;

use App\Kernel\Presentation\Http\Request;
use App\Kernel\Presentation\Http\Response;

final readonly class UserSearchEndpoint
{
    /** @param callable(): SearchUsersController $controller */
    public function __construct(private mixed $controller) {}

    public function search(Request $request): Response
    {
        $result = ($this->controller)()->search($request->query);

        return Response::json($result->data);
    }
}
