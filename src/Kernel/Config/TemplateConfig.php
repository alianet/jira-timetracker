<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Config;

final readonly class TemplateConfig
{
    public function __construct(public string $variant) {}

    public static function fromConfig(Config $config): self
    {
        return new self($config->choice('TEMPLATE', ['default', 'compact'], 'default'));
    }
}
