<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Presentation\Http;

use Psr\Log\LoggerInterface;

use function Safe\error_log;
use function Safe\ob_end_clean;
use function Safe\ob_end_flush;
use function Safe\ob_start;

final class ApplicationErrorHandler
{
    /** @var array<string, array{title: string, eyebrow: string, heading: string, message: string, back: string}> */
    private const array MESSAGES = [
        'pl' => [
            'title' => 'Błąd',
            'eyebrow' => 'Nie udało się wykonać operacji',
            'heading' => 'Wystąpił błąd',
            'message' => 'Wystąpił nieoczekiwany błąd. Spróbuj ponownie później.',
            'back' => 'Wróć do raportu',
        ],
        'en' => [
            'title' => 'Error',
            'eyebrow' => 'The operation could not be completed',
            'heading' => 'Something went wrong',
            'message' => 'An unexpected error occurred. Please try again later.',
            'back' => 'Back to report',
        ],
        'de' => [
            'title' => 'Fehler',
            'eyebrow' => 'Der Vorgang konnte nicht abgeschlossen werden',
            'heading' => 'Ein Fehler ist aufgetreten',
            'message' => 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.',
            'back' => 'Zurück zum Bericht',
        ],
        'cs' => [
            'title' => 'Chyba',
            'eyebrow' => 'Operaci se nepodařilo dokončit',
            'heading' => 'Došlo k chybě',
            'message' => 'Došlo k neočekávané chybě. Zkuste to prosím později.',
            'back' => 'Zpět na přehled',
        ],
        'sk' => [
            'title' => 'Chyba',
            'eyebrow' => 'Operáciu sa nepodarilo dokončiť',
            'heading' => 'Vyskytla sa chyba',
            'message' => 'Vyskytla sa neočakávaná chyba. Skúste to znova neskôr.',
            'back' => 'Späť na prehľad',
        ],
        'fr' => [
            'title' => 'Erreur',
            'eyebrow' => 'L’opération n’a pas pu être effectuée',
            'heading' => 'Une erreur est survenue',
            'message' => 'Une erreur inattendue est survenue. Veuillez réessayer plus tard.',
            'back' => 'Retour au rapport',
        ],
    ];

    /** @var array<string, mixed> */
    private array $server = [];
    private ?int $outputBufferLevel = null;
    private bool $handled = false;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ?string $fallbackLogPath = null,
    ) {}

    /** @param array<string, mixed> $server */
    public function register(array $server): void
    {
        $this->server = $server;
        $this->outputBufferLevel = ob_get_level();
        ob_start();
        register_shutdown_function($this->handleShutdown(...));
    }

    public function complete(): void
    {
        if ($this->outputBufferLevel === null) {
            return;
        }

        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_flush();
        }
        $this->outputBufferLevel = null;
    }

    /** @param array<string, mixed> $server */
    public function handle(\Throwable $exception, array $server): void
    {
        if ($this->handled) {
            return;
        }
        $this->handled = true;
        $this->discardBufferedOutput();

        $method = is_string($server['REQUEST_METHOD'] ?? null) ? $server['REQUEST_METHOD'] : 'GET';
        $path = $this->requestPath($server);
        $locale = $this->locale($server);
        $messages = self::MESSAGES[$locale];

        try {
            $this->logger->critical('Fatal application error.', [
                'exception' => $exception,
                'method' => $method,
                'path' => $path,
            ]);
        } catch (\Throwable $loggingException) {
            if ($this->fallbackLogPath !== null) {
                try {
                    error_log(sprintf(
                        "Fatal application error: %s\nLogging failure: %s\n",
                        (string) $exception,
                        (string) $loggingException,
                    ), 3, $this->fallbackLogPath);
                } catch (\Throwable) {
                }
            }
        }

        http_response_code(500);
        if (!headers_sent()) {
            header_remove();
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
        }

        if (str_starts_with($path, '/api/')) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
            }
            echo json_encode(['error' => $messages['message']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            return;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo <<<HTML
            <!doctype html>
            <html lang="{$locale}">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>{$messages['title']} | Time Tracker</title>
                <link rel="stylesheet" href="/css/app.css">
            </head>
            <body>
                <main class="container">
                    <section class="error-card">
                        <p class="eyebrow">{$messages['eyebrow']}</p>
                        <h1>{$messages['heading']}</h1>
                        <p>{$messages['message']}</p>
                        <a class="button error-back" href="/">{$messages['back']}</a>
                    </section>
                </main>
            </body>
            </html>
            HTML;
    }

    private function handleShutdown(): void
    {
        if ($this->handled || $this->outputBufferLevel === null) {
            return;
        }

        $error = error_get_last();
        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            return;
        }

        $this->handle(new \ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line'],
        ), $this->server);
    }

    private function discardBufferedOutput(): void
    {
        if ($this->outputBufferLevel === null) {
            return;
        }

        while (ob_get_level() > $this->outputBufferLevel) {
            try {
                ob_end_clean();
            } catch (\Throwable) {
                break;
            }
        }
        $this->outputBufferLevel = null;
    }

    /** @param array<string, mixed> $server */
    private function requestPath(array $server): string
    {
        $uri = is_string($server['REQUEST_URI'] ?? null) ? $server['REQUEST_URI'] : '/';

        return explode('?', $uri, 2)[0] ?: '/';
    }

    /** @param array<string, mixed> $server */
    private function locale(array $server): string
    {
        $query = [];
        $requestUri = is_string($server['REQUEST_URI'] ?? null) ? $server['REQUEST_URI'] : '/';
        parse_str(explode('?', $requestUri, 2)[1] ?? '', $query);
        $requestedLocale = strtolower(is_string($query['locale'] ?? null) ? $query['locale'] : '');
        if (isset(self::MESSAGES[$requestedLocale])) {
            return $requestedLocale;
        }

        $cookies = is_array($server['COOKIE'] ?? null) ? $server['COOKIE'] : [];
        $cookieLocale = strtolower(is_string($cookies['jira_timetracker_locale'] ?? null) ? $cookies['jira_timetracker_locale'] : '');
        if (isset(self::MESSAGES[$cookieLocale])) {
            return $cookieLocale;
        }

        $acceptedLanguages = is_string($server['HTTP_ACCEPT_LANGUAGE'] ?? null) ? $server['HTTP_ACCEPT_LANGUAGE'] : '';
        foreach (explode(',', $acceptedLanguages) as $acceptedLanguage) {
            $language = strtolower(trim(explode(';', $acceptedLanguage, 2)[0]));
            $primaryLanguage = explode('-', str_replace('_', '-', $language), 2)[0];
            if (isset(self::MESSAGES[$primaryLanguage])) {
                return $primaryLanguage;
            }
        }

        return 'pl';
    }
}
