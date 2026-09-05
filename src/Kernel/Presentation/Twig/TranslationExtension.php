<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Presentation\Twig;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TranslationExtension extends AbstractExtension
{
    public function __construct(private TranslatorInterface $translator) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('t', $this->translate(...)),
        ];
    }

    /**
     * @param array<string, string|int|float> $parameters
     */
    public function translate(string $key, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $this->translator->trans($key, $parameters, $domain, $locale);
    }
}
