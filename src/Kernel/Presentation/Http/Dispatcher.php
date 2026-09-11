<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Presentation\Http;

use App\Kernel\Session\Session;

final readonly class Dispatcher
{
    public function __construct(
        private Router $router,
        private RequestAuthorization $authorization,
        private Session $session,
    ) {}

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     */
    public function dispatch(string $method, string $path, array $query, array $post): Response
    {
        [$route, $attributes] = $this->router->match($method, $path);
        if (!$route->public && null !== $response = $this->authorization->requireAuthenticated()) {
            return str_starts_with($path, '/api/') && $response->status === 401
                ? Response::json(['error' => 'authentication_required'], 401)
                : $response;
        }

        $request = new Request($method, $path, $query, $post, $attributes, $this->session);

        return ($route->controller)($request);
    }
}
