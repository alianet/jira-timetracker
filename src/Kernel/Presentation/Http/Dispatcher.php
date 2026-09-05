<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Presentation\Http;

final readonly class Dispatcher
{
    public function __construct(
        private Router $router,
        /** @var \Closure(array<array-key, mixed>): ?Response */
        private \Closure $requireAuthenticated,
    ) {}

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<array-key, mixed> $session
     */
    public function dispatch(string $method, string $path, array $query, array $post, array &$session): Response
    {
        [$route, $attributes] = $this->router->match($method, $path);
        if (!$route->public && null !== $response = ($this->requireAuthenticated)($session)) {
            return str_starts_with($path, '/api/') && $response->status === 401
                ? Response::json(['error' => 'authentication_required'], 401)
                : $response;
        }

        $request = new Request($method, $path, $query, $post, $attributes, $session);

        return ($route->controller)($request);
    }
}
