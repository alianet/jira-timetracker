<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Kernel\Presentation\Twig;

use App\Kernel\Presentation\Twig\CalendarExtension;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class CalendarExtensionTest extends TestCase
{
    public function testDayTitleUsesTranslatorForWeekendAlertAndHoliday(): void
    {
        $translator = new class implements TranslatorInterface, LocaleAwareInterface {
            private string $locale = 'pl';

            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                $messages = [
                    'calendar.day.weekend' => 'Weekend',
                    'calendar.day.alert' => 'Below the daily limit',
                    'calendar.holiday.epiphany' => 'Epiphany',
                    'calendar.month.1' => 'January',
                ];

                return $messages[$id] ?? $id;
            }

            public function setLocale(string $locale): void
            {
                $this->locale = $locale;
            }

            public function getLocale(): string
            {
                return $this->locale;
            }
        };

        $extension = new CalendarExtension($translator);

        self::assertSame('day-today', $extension->dayClasses(['weekend' => false, 'holidays' => [], 'alert' => false, 'today' => true]));
        self::assertSame('Weekend', $extension->dayTitle(['weekend' => true, 'holidays' => [], 'alert' => false]));
        self::assertSame('Below the daily limit', $extension->dayTitle(['weekend' => false, 'holidays' => [], 'alert' => true]));
        self::assertSame('Epiphany', $extension->dayTitle(['weekend' => false, 'holidays' => ['epiphany'], 'alert' => false]));
    }

    public function testGetGlobalsReturnsTranslatedMonths(): void
    {
        $translator = new class implements TranslatorInterface, LocaleAwareInterface {
            private string $locale = 'pl';

            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return $id === 'calendar.month.1' ? 'January' : $id;
            }

            public function setLocale(string $locale): void
            {
                $this->locale = $locale;
            }

            public function getLocale(): string
            {
                return $this->locale;
            }
        };

        $globals = new CalendarExtension($translator)->getGlobals();

        self::assertSame('January', $globals['months'][1]);
        self::assertCount(12, $globals['months']);
    }

    public function testMonthsAreRegisteredAsTwigGlobals(): void
    {
        $translator = new class implements TranslatorInterface, LocaleAwareInterface {
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
        $twig = new Environment(new ArrayLoader(['months' => '{% for number, name in months %}{{ number }}={{ name }};{% endfor %}']));
        $twig->addExtension(new CalendarExtension($translator));

        $rendered = $twig->render('months');

        self::assertStringStartsWith('1=calendar.month.1;', $rendered);
        self::assertStringContainsString('12=calendar.month.12;', $rendered);
    }
}
