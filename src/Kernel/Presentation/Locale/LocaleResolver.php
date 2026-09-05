<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Presentation\Locale;

use App\Kernel\Config\LocaleConfig;
use App\Kernel\Support\ApiValue;

use function Safe\parse_url;
use function Safe\preg_split;

final readonly class LocaleResolver
{
    public function __construct(private LocaleConfig $config) {}

    /**
     * @param array<string, mixed> $server
     */
    public function resolve(array $server, ?string $sessionLocale): string
    {
        $requestedLocale = $this->requestedLocale($server);
        if ($requestedLocale !== null) {
            return $requestedLocale;
        }

        $cookieLocale = $this->cookieLocale($server);
        if ($cookieLocale !== null) {
            return $cookieLocale;
        }

        if (is_string($sessionLocale) && in_array($sessionLocale, $this->config->locales, true)) {
            return $sessionLocale;
        }

        $browserLocale = $this->browserLocale($server);
        if ($browserLocale !== null) {
            return $browserLocale;
        }

        return $this->config->defaultLocale;
    }

    /**
     * @param array<string, mixed> $server
     */
    public function redirectUriWithoutRequestedLocale(array $server): ?string
    {
        if ($this->requestedLocale($server) === null) {
            return null;
        }

        $requestUri = ApiValue::stringValue($server['REQUEST_URI'] ?? '/');
        $path = ApiValue::stringValue(parse_url($requestUri, PHP_URL_PATH)) ?: '/';
        $path = '/' . ltrim($path, '/');
        $query = [];
        parse_str(ApiValue::stringValue(parse_url($requestUri, PHP_URL_QUERY)), $query);
        unset($query['locale']);

        $queryString = http_build_query($query);

        return $queryString === '' ? $path : $path . '?' . $queryString;
    }

    /**
     * @param array<string, mixed> $server
     */
    private function requestedLocale(array $server): ?string
    {
        $query = [];
        parse_str(ApiValue::stringValue(parse_url(ApiValue::stringValue($server['REQUEST_URI'] ?? null), PHP_URL_QUERY)), $query);
        $locale = strtolower(trim(ApiValue::stringValue($query['locale'] ?? null)));

        return in_array($locale, $this->config->locales, true) ? $locale : null;
    }

    /**
     * @param array<string, mixed> $server
     */
    private function cookieLocale(array $server): ?string
    {
        $cookies = $server['COOKIE'] ?? [];
        if (!is_array($cookies)) {
            return null;
        }
        /** @var array<string, mixed> $cookies */
        $locale = strtolower(trim(ApiValue::stringValue($cookies[$this->config->cookieName] ?? null)));

        return in_array($locale, $this->config->locales, true) ? $locale : null;
    }

    /**
     * @param array<string, mixed> $server
     */
    private function browserLocale(array $server): ?string
    {
        $header = ApiValue::stringValue($server['HTTP_ACCEPT_LANGUAGE'] ?? null);

        if ($header === '') {
            return null;
        }

        foreach (preg_split('/\s*,\s*/', $header) ?: [] as $item) {
            if ($item === '') {
                continue;
            }

            [$tag] = explode(';', $item, 2);
            $tag = strtolower(trim($tag));
            if ($tag === '') {
                continue;
            }

            if (in_array($tag, $this->config->locales, true)) {
                return $tag;
            }

            $primary = strtolower(strtok($tag, '-_') ?: $tag);
            if (in_array($primary, $this->config->locales, true)) {
                return $primary;
            }
        }

        return null;
    }
}
