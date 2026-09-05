<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Kernel\Config;

use App\Kernel\Config\LocaleConfig;
use App\Kernel\Exception\ApplicationConfigException;
use Tests\TestCase;

final class LocaleConfigTest extends TestCase
{
    public function testItReadsLocalesAndCookieSettingsFromEnv(): void
    {
        $config = $this->createConfig([
            'APP_LOCALES' => 'pl,en',
            'APP_DEFAULT_LOCALE' => 'en',
            'APP_LOCALE_COOKIE_NAME' => 'tt_locale',
            'APP_LOCALE_COOKIE_TTL' => '86400',
        ]);

        $localeConfig = LocaleConfig::fromConfig($config);

        self::assertSame(['pl', 'en'], $localeConfig->locales);
        self::assertSame('en', $localeConfig->defaultLocale);
        self::assertSame('tt_locale', $localeConfig->cookieName);
        self::assertSame(86400, $localeConfig->cookieTtl);
    }

    public function testItRejectsUnknownDefaultLocale(): void
    {
        $config = $this->createConfig([
            'APP_LOCALES' => 'pl,en',
            'APP_DEFAULT_LOCALE' => 'de',
        ]);

        $this->expectException(ApplicationConfigException::class);
        LocaleConfig::fromConfig($config);
    }

    public function testItEnablesAllBundledLocalesByDefault(): void
    {
        $localeConfig = LocaleConfig::fromConfig($this->createConfig([]));

        self::assertSame(['pl', 'en', 'de', 'cs', 'sk', 'fr'], $localeConfig->locales);
        self::assertSame('pl', $localeConfig->defaultLocale);
    }
}
