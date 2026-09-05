<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Infrastructure\Translation;

use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

final class TranslatorFactory
{
    /**
     * @param list<string> $locales
     */
    public function create(string $translationsDirectory, array $locales, string $defaultLocale): Translator
    {
        $translator = new Translator($defaultLocale);
        $translator->addLoader('array', new ArrayLoader());
        $translator->setFallbackLocales([$defaultLocale]);

        foreach ($locales as $locale) {
            $path = rtrim($translationsDirectory, '/') . '/' . $locale . '.php';

            if (!is_file($path)) {
                continue;
            }

            $translator->addResource('array', require $path, $locale);
        }

        return $translator;
    }
}
