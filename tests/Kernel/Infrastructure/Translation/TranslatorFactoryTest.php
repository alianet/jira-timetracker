<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Kernel\Infrastructure\Translation;

use App\Kernel\Infrastructure\Translation\TranslatorFactory;
use Tests\TestCase;

final class TranslatorFactoryTest extends TestCase
{
    public function testItLoadsTranslationResourcesAndFallsBackToDefaultLocale(): void
    {
        $directory = $this->createTempDirectory();
        $this->writeFile($directory, 'pl.php', '<?php return [\'app.name\' => \'Planer\'];');
        $this->writeFile($directory, 'en.php', '<?php return [\'app.name\' => \'Tracker\'];');

        $translator = new TranslatorFactory()->create($directory, ['pl', 'en'], 'pl');

        self::assertSame('Planer', $translator->trans('app.name'));
        $translator->setLocale('en');
        self::assertSame('Tracker', $translator->trans('app.name'));
    }

    public function testBundledTranslationsContainTheSameKeys(): void
    {
        $translationsDirectory = dirname(__DIR__, 4) . '/translations';
        $englishTranslations = require $translationsDirectory . '/en.php';

        foreach (['pl', 'de', 'cs', 'sk', 'fr'] as $locale) {
            $translations = require $translationsDirectory . '/' . $locale . '.php';

            self::assertSame(
                array_keys($englishTranslations),
                array_keys($translations),
                "Translation keys for {$locale} do not match the English catalogue.",
            );
        }
    }
}
