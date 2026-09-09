<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Kernel\Presentation\Http;

use App\Kernel\Exception\ApplicationRuntimeException;
use App\Kernel\Exception\RateLimitExceededException;
use App\Kernel\Support\ApiValue;
use Psr\Log\LoggerInterface;
use Safe\Exceptions\UrlException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

use function Safe\parse_url;

final readonly class FrontController
{
    public function __construct(
        private Dispatcher $dispatcher,
        private Environment $twig,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $server
     * @throws UrlException
     */
    public function handle(array $server): void
    {
        $path = ApiValue::stringValue(
            parse_url(ApiValue::stringValue($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/',
        );
        $method = ApiValue::stringValue($server['REQUEST_METHOD'] ?? 'GET');

        $this->logger->debug('Handling HTTP request.', [
            'method' => $method,
            'path' => $path,
        ]);

        try {
            $response = $this->dispatcher->dispatch($method, $path, $_GET, $_POST, $_SESSION);
            http_response_code($response->status);
            foreach ($response->headers as $name => $value) {
                header($name . ': ' . $value);
            }
            echo $response->body;

            $this->logger->info('HTTP response sent.', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status,
            ]);
        } catch (ResourceNotFoundException) {
            $this->logger->notice('HTTP route was not found.', ['method' => $method, 'path' => $path]);
            http_response_code(404);
            echo $this->twig->render('error.html.twig', ['message' => $this->translator->trans('error.not_found')]);
        } catch (MethodNotAllowedException $exception) {
            $this->logger->notice('HTTP method is not allowed for route.', [
                'method' => $method,
                'path' => $path,
                'allowed_methods' => $exception->getAllowedMethods(),
            ]);
            http_response_code(405);
            header('Allow: ' . implode(', ', $exception->getAllowedMethods()));
            echo $this->twig->render('error.html.twig', ['message' => $this->translator->trans('error.method_not_allowed')]);
        } catch (RateLimitExceededException $exception) {
            $this->logger->warning('External API rate limit exceeded during HTTP request.', [
                'exception' => $exception,
                'method' => $method,
                'path' => $path,
                'retry_after_seconds' => $exception->retryAfterSeconds,
            ]);

            http_response_code(429);
            if ($exception->retryAfterSeconds !== null) {
                header('Retry-After: ' . $exception->retryAfterSeconds);
            }
            $this->sendError($path, $exception->getMessage());
        } catch (\Throwable $exception) {
            $this->logger->error('Unhandled exception during HTTP request.', [
                'exception' => $exception,
                'method' => $method,
                'path' => $path,
            ]);

            http_response_code(500);
            $message = $exception instanceof ApplicationRuntimeException
                ? $exception->getMessage()
                : $this->translator->trans('error.unexpected');
            $this->sendError($path, $message);
        }
    }

    private function sendError(string $path, string $message): void
    {
        if (str_starts_with($path, '/api/')) {
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
            echo json_encode(['error' => $message], JSON_THROW_ON_ERROR);

            return;
        }

        echo $this->twig->render('error.html.twig', ['message' => $message]);
    }
}
