<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Presentation\Http;

use App\Kernel\Presentation\Http\Request;
use App\Kernel\Presentation\Http\Response;

final readonly class IssueSearchEndpoint
{
    /** @param callable(array<array-key, mixed>&): SearchIssuesController $controller */
    public function __construct(private mixed $controller) {}

    public function search(Request $request): Response
    {
        $result = ($this->controller)($request->session)->search($request->query);

        return Response::json($result->data);
    }
}
