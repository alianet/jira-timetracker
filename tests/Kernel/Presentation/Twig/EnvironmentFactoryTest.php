<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Kernel\Presentation\Twig;

use App\Kernel\Presentation\Twig\EnvironmentFactory;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\TestCase;

final class EnvironmentFactoryTest extends TestCase
{
    public function testCreateRegistersApplicationExtensions(): void
    {
        $templatesDirectory = $this->createTempDirectory();
        $this->writeFile(
            $templatesDirectory,
            'extensions.html.twig',
            "{{ t('report.month') }}|{{ months[1] }}|{{ day|calendar_day_classes }}|{{ day|calendar_day_title }}",
        );
        $translator = new class implements TranslatorInterface, LocaleAwareInterface {
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return [
                    'report.month' => 'Miesiąc',
                    'calendar.month.1' => 'Styczeń',
                    'calendar.day.weekend' => 'Weekend',
                ][$id] ?? $id;
            }

            public function setLocale(string $locale): void {}

            public function getLocale(): string
            {
                return 'pl';
            }
        };

        $twig = new EnvironmentFactory()->create($templatesDirectory, $translator);
        $rendered = $twig->render('extensions.html.twig', [
            'day' => ['weekend' => true, 'holidays' => [], 'alert' => false],
        ]);

        self::assertSame('Miesiąc|Styczeń|day-weekend|Weekend', $rendered);
    }

    public function testLocaleSwitcherIsHiddenWhenOnlyOneLocaleIsAvailable(): void
    {
        $twig = new EnvironmentFactory()->create(dirname(__DIR__, 4) . '/templates', $this->translator());

        $rendered = $twig->render('_locale_switcher.html.twig', [
            'availableLocales' => ['pl'],
            'currentLocale' => 'pl',
        ]);

        self::assertStringNotContainsString('locale-select', $rendered);
    }

    public function testLocaleSwitcherIsShownWhenMultipleLocalesAreAvailable(): void
    {
        $twig = new EnvironmentFactory()->create(dirname(__DIR__, 4) . '/templates', $this->translator());

        $rendered = $twig->render('_locale_switcher.html.twig', [
            'availableLocales' => ['pl', 'en'],
            'currentLocale' => 'pl',
        ]);

        self::assertStringContainsString('id="locale-select"', $rendered);
        self::assertStringContainsString('<option value="pl" selected>', $rendered);
        self::assertStringContainsString('<option value="en" >', $rendered);
    }

    private function translator(): TranslatorInterface&LocaleAwareInterface
    {
        return new class implements TranslatorInterface, LocaleAwareInterface {
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return $id;
            }

            public function setLocale(string $locale): void {}

            public function getLocale(): string
            {
                return 'pl';
            }
        };
    }
}
