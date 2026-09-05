<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Kernel\Presentation\Locale;

use App\Kernel\Config\LocaleConfig;
use App\Kernel\Presentation\Locale\LocaleResolver;
use Tests\TestCase;

final class LocaleResolverTest extends TestCase
{
    public function testLocaleFromQueryTakesPriorityOverEverythingElse(): void
    {
        $resolver = $this->resolver();

        self::assertSame('en', $resolver->resolve([
            'REQUEST_URI' => '/?locale=en',
            'COOKIE' => ['jira_timetracker_locale' => 'pl'],
            'HTTP_ACCEPT_LANGUAGE' => 'pl-PL,pl;q=0.9',
        ], 'pl'));
    }

    public function testLocaleFromCookieTakesPriorityOverSessionAndBrowser(): void
    {
        $resolver = $this->resolver();

        self::assertSame('pl', $resolver->resolve([
            'REQUEST_URI' => '/',
            'COOKIE' => ['jira_timetracker_locale' => 'pl'],
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
        ], 'en'));
    }

    public function testBrowserLocaleIsUsedWhenNoExplicitPreferenceExists(): void
    {
        $resolver = $this->resolver();

        self::assertSame('de', $resolver->resolve([
            'REQUEST_URI' => '/',
            'COOKIE' => [],
            'HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9,en-US;q=0.8',
        ], null));
    }

    public function testDefaultLocaleIsUsedAsFinalFallback(): void
    {
        $resolver = $this->resolver();

        self::assertSame('pl', $resolver->resolve([
            'REQUEST_URI' => '/',
            'COOKIE' => ['jira_timetracker_locale' => 'it'],
            'HTTP_ACCEPT_LANGUAGE' => 'it-IT,it;q=0.9',
        ], null));
    }

    public function testFrenchBrowserLocaleIsRecognizedFromRegionalTag(): void
    {
        self::assertSame('fr', $this->resolver()->resolve([
            'REQUEST_URI' => '/',
            'COOKIE' => [],
            'HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9,en;q=0.8',
        ], null));
    }

    public function testRedirectUriRemovesLocaleAndPreservesOtherQueryParameters(): void
    {
        self::assertSame('/?month=8&year=2026', $this->resolver()->redirectUriWithoutRequestedLocale([
            'REQUEST_URI' => '/?month=8&locale=en&year=2026',
        ]));
    }

    public function testRedirectUriPreservesRequestPath(): void
    {
        self::assertSame('/report?accountId=abc', $this->resolver()->redirectUriWithoutRequestedLocale([
            'REQUEST_URI' => '/report?locale=pl&accountId=abc',
        ]));
    }

    public function testRedirectUriIsNullWithoutValidRequestedLocale(): void
    {
        self::assertNull($this->resolver()->redirectUriWithoutRequestedLocale([
            'REQUEST_URI' => '/?locale=it',
        ]));
        self::assertNull($this->resolver()->redirectUriWithoutRequestedLocale([
            'REQUEST_URI' => '/?month=8',
        ]));
    }

    private function resolver(): LocaleResolver
    {
        $config = $this->createConfig([
            'APP_LOCALES' => 'pl,en,de,cs,sk,fr',
            'APP_DEFAULT_LOCALE' => 'pl',
            'APP_LOCALE_COOKIE_NAME' => 'jira_timetracker_locale',
            'APP_LOCALE_COOKIE_TTL' => '86400',
        ]);

        return new LocaleResolver(LocaleConfig::fromConfig($config));
    }
}
