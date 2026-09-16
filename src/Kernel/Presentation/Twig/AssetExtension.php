<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Presentation\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function Safe\filemtime;

final class AssetExtension extends AbstractExtension
{
    public function __construct(private readonly string $publicDirectory) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('asset', $this->asset(...))];
    }

    public function asset(string $path): string
    {
        $file = rtrim($this->publicDirectory, '/') . '/' . ltrim($path, '/');

        return is_file($file) ? $path . '?v=' . filemtime($file) : $path;
    }
}
