<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Presentation\Http;

use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route as SymfonyRoute;
use Symfony\Component\Routing\RouteCollection;

final readonly class Router
{
    private RouteCollection $collection;

    /** @param list<Route> $routes */
    public function __construct(private array $routes)
    {
        $this->collection = new RouteCollection();
        foreach ($routes as $index => $route) {
            $this->collection->add((string) $index, new SymfonyRoute(
                $route->path,
                ['_route_index' => $index],
                $route->requirements,
                methods: $route->methods,
            ));
        }
    }

    /** @return array{0: Route, 1: array<string, mixed>} */
    public function match(string $method, string $path): array
    {
        $matched = new UrlMatcher($this->collection, new RequestContext(method: strtoupper($method)))->match($path);
        $index = $matched['_route_index'] ?? null;
        if (!is_int($index) || !isset($this->routes[$index])) {
            throw new ResourceNotFoundException();
        }
        unset($matched['_route'], $matched['_route_index']);

        return [$this->routes[$index], $matched];
    }
}
