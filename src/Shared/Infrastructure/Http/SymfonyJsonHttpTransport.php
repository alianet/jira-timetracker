<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class SymfonyJsonHttpTransport implements JsonHttpTransport
{
    public function __construct(
        private string $baseUrl,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function request(string $method, string $path, ?array $body = null, ?array $query = null): array
    {
        $startedAt = microtime(true);
        $options = ['headers' => ['Accept: application/json', 'Content-Type: application/json'], 'timeout' => 30];
        if ($body !== null) {
            $options['json'] = $body;
        }
        if ($query !== null) {
            $options['query'] = $query;
        }

        $this->logger->debug('Sending HTTP request to external service.', [
            'method' => $method,
            'path' => $path,
            'has_body' => $body !== null,
            'query_parameters' => $query === null ? [] : array_keys($query),
        ]);

        try {
            $response = $this->httpClient->request($method, rtrim($this->baseUrl, '/') . $path, $options);
            $status = $response->getStatusCode();
            $headers = $response->getHeaders(false);
            $content = $response->getContent(false);
        } catch (\Throwable $exception) {
            $this->logger->error('External HTTP request failed.', [
                'exception' => $exception,
                'method' => $method,
                'path' => $path,
                'duration_ms' => self::duration($startedAt),
            ]);

            throw HttpTransportException::connectionFailed($exception);
        }
        if ($status < 200 || $status >= 300) {
            $this->logger->error('External service returned an unsuccessful response.', [
                'method' => $method,
                'path' => $path,
                'status' => $status,
                'duration_ms' => self::duration($startedAt),
            ]);

            throw HttpTransportException::unsuccessfulResponse($status, $content, $headers);
        }

        $this->logger->debug('External HTTP request completed.', [
            'method' => $method,
            'path' => $path,
            'status' => $status,
            'duration_ms' => self::duration($startedAt),
        ]);

        if ($content === '') {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger->error('External service returned invalid JSON.', [
                'exception' => $exception,
                'method' => $method,
                'path' => $path,
                'status' => $status,
            ]);

            throw $exception;
        }
        if (!is_array($decoded)) {
            $this->logger->error('External service returned an unexpected JSON payload.', [
                'method' => $method,
                'path' => $path,
                'status' => $status,
            ]);

            throw HttpTransportException::unexpectedJsonPayload();
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private static function duration(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
