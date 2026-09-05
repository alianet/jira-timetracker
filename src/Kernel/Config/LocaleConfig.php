<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Config;

use App\Kernel\Exception\ApplicationConfigException;

use function Safe\preg_split;

final readonly class LocaleConfig
{
    /**
     * @param non-empty-list<string> $locales
     */
    private function __construct(
        public array $locales,
        public string $defaultLocale,
        public string $cookieName,
        public int $cookieTtl,
    ) {}

    public static function fromConfig(Config $config): self
    {
        $locales = self::parseLocales($config->nullable('APP_LOCALES') ?? 'pl,en,de,cs,sk,fr');
        $defaultLocale = strtolower(trim($config->nullable('APP_DEFAULT_LOCALE') ?? $locales[0]));
        $cookieName = trim($config->nullable('APP_LOCALE_COOKIE_NAME') ?? 'jira_timetracker_locale');
        $cookieTtl = self::parseCookieTtl($config->nullable('APP_LOCALE_COOKIE_TTL') ?? '31536000');

        if (!in_array($defaultLocale, $locales, true)) {
            throw ApplicationConfigException::invalidValue(
                'APP_DEFAULT_LOCALE',
                'jedna z dostępnych lokalizacji: ' . implode(', ', $locales),
            );
        }

        if ($cookieName === '') {
            throw ApplicationConfigException::invalidValue('APP_LOCALE_COOKIE_NAME', 'niepusta nazwa ciasteczka');
        }

        return new self($locales, $defaultLocale, $cookieName, $cookieTtl);
    }

    /**
     * @return non-empty-list<string>
     */
    private static function parseLocales(string $value): array
    {
        $locales = array_values(array_filter(array_map(
            static fn(string $locale): string => strtolower(trim($locale)),
            preg_split('/\s*,\s*/', $value) ?: [],
        ), static fn(string $locale): bool => $locale !== ''));

        if ($locales === []) {
            throw ApplicationConfigException::invalidValue('APP_LOCALES', 'lista kodów języków oddzielonych przecinkami');
        }

        return $locales;
    }

    private static function parseCookieTtl(string $value): int
    {
        if (!ctype_digit($value)) {
            throw ApplicationConfigException::invalidValue('APP_LOCALE_COOKIE_TTL', 'dodatnia liczba sekund');
        }

        $ttl = (int) $value;

        if ($ttl <= 0) {
            throw ApplicationConfigException::invalidValue('APP_LOCALE_COOKIE_TTL', 'dodatnia liczba sekund');
        }

        return $ttl;
    }
}
