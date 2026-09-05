<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Presentation\Twig;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class EnvironmentFactory
{
    public function create(string $templatesDirectory, TranslatorInterface $translator): Environment
    {
        $twig = new Environment(new FilesystemLoader($templatesDirectory));
        $twig->addExtension(new CalendarExtension($translator));
        $twig->addExtension(new TranslationExtension($translator));

        return $twig;
    }
}
