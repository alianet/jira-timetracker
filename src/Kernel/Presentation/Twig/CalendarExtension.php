<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Presentation\Twig;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;

final class CalendarExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private TranslatorInterface $translator) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('calendar_day_classes', $this->dayClasses(...)),
            new TwigFilter('calendar_day_title', $this->dayTitle(...)),
        ];
    }

    /** @param array{weekend: bool, holidays: list<string>, alert: bool, today?: bool} $day */
    public function dayClasses(array $day): string
    {
        return implode(' ', array_filter([
            $day['weekend'] ? 'day-weekend' : null,
            $day['holidays'] !== [] ? 'day-holiday' : null,
            $day['alert'] ? 'day-alert' : null,
            ($day['today'] ?? false) ? 'day-today' : null,
        ]));
    }

    /** @param array{weekend: bool, holidays: list<string>, alert: bool} $day */
    public function dayTitle(array $day): string
    {
        if ($day['holidays'] !== []) {
            return $this->holidayNames($day['holidays']);
        }

        if ($day['weekend']) {
            return $this->translator->trans('calendar.day.weekend');
        }

        return $day['alert'] ? $this->translator->trans('calendar.day.alert') : '';
    }

    /** @param list<string> $holidays */
    private function holidayNames(array $holidays): string
    {
        return implode(', ', array_map(
            fn(string $holiday): string => $this->translator->trans('calendar.holiday.' . $holiday),
            $holidays,
        ));
    }

    /**
     * @return array{months: array<int, string>}
     */
    public function getGlobals(): array
    {
        return [
            'months' => [
                1 => $this->translator->trans('calendar.month.1'),
                2 => $this->translator->trans('calendar.month.2'),
                3 => $this->translator->trans('calendar.month.3'),
                4 => $this->translator->trans('calendar.month.4'),
                5 => $this->translator->trans('calendar.month.5'),
                6 => $this->translator->trans('calendar.month.6'),
                7 => $this->translator->trans('calendar.month.7'),
                8 => $this->translator->trans('calendar.month.8'),
                9 => $this->translator->trans('calendar.month.9'),
                10 => $this->translator->trans('calendar.month.10'),
                11 => $this->translator->trans('calendar.month.11'),
                12 => $this->translator->trans('calendar.month.12'),
            ],
        ];
    }
}
