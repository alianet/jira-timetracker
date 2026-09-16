<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Presentation\Http;

use App\Kernel\Presentation\Http\Request;
use App\Kernel\Presentation\Http\Response;

final readonly class DailyOverviewEndpoint
{
    /** @param callable(): DailyOverviewController $controller */
    public function __construct(private mixed $controller) {}

    public function show(Request $request): Response
    {
        return Response::json(($this->controller)()->show()->data);
    }
}
