<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Kernel\Config;

use App\Kernel\Config\TemplateConfig;
use Tests\TestCase;

final class TemplateConfigTest extends TestCase
{
    public function testItUsesDefaultVariantWhenTemplateIsMissing(): void
    {
        self::assertSame('default', TemplateConfig::fromConfig($this->createConfig([]))->variant);
    }

    public function testItReadsCompactVariant(): void
    {
        self::assertSame('compact', TemplateConfig::fromConfig($this->createConfig([
            'TEMPLATE' => 'compact',
        ]))->variant);
    }

    public function testItRejectsUnknownVariant(): void
    {
        $this->expectException(\RuntimeException::class);
        TemplateConfig::fromConfig($this->createConfig([
            'TEMPLATE' => 'wide',
        ]));
    }
}
