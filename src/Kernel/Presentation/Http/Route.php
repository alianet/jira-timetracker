<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Presentation\Http;

final readonly class Route
{
    /**
     * @param list<string> $methods
     * @param callable(Request): Response $controller
     */
    public function __construct(
        public string $name,
        public string $path,
        public array $methods,
        public mixed $controller,
        public bool $public = false,
        /** @var array<string, string> */
        public array $requirements = [],
    ) {}
}
