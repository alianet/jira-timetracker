<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Infrastructure\Atlassian;

use App\Kernel\Exception\ApplicationRuntimeException as WorkLogRuntimeException;
use App\Kernel\Exception\RateLimitExceededException;
use App\Shared\Infrastructure\Http\HttpTransportException;
use App\Shared\Infrastructure\Http\JsonHttpTransport;
use App\Shared\Infrastructure\Jira\JiraErrorResponse;
use JsonException;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AtlassianHttpClient implements AtlassianTransport
{
    public function __construct(
        private JsonHttpTransport $transport,
        private TranslatorInterface $translator,
    ) {}

    public function request(string $method, string $path, ?array $query = null): array
    {
        try {
            return $this->transport->request($method, $path, null, $query);
        } catch (HttpTransportException $exception) {
            if ($exception->reason === 'unsuccessful_response') {
                if ($exception->status === 429) {
                    throw $this->rateLimitException($exception);
                }
                throw WorkLogRuntimeException::create($this->errorMessage($exception->status ?? 0, $exception->responseBody));
            }
            if ($exception->reason === 'unexpected_json_payload') {
                throw WorkLogRuntimeException::create($this->translator->trans('jira.invalid_json'));
            }
            throw WorkLogRuntimeException::create(
                $this->translator->trans('jira.connection_failed', ['{message}' => $exception->getMessage()]),
                $exception,
            );
        }
    }

    private function rateLimitException(HttpTransportException $exception): RateLimitExceededException
    {
        $retryAfter = $exception->retryAfterSeconds();

        return RateLimitExceededException::withRetryAfter(
            JiraErrorResponse::rateLimitMessage($exception, $this->translator),
            $retryAfter,
            $exception,
        );
    }

    private function errorMessage(int $status, string $content): string
    {
        $messages = [];
        try {
            $details = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $details = null;
        }

        if (is_array($details)) {
            foreach (is_array($details['errorMessages'] ?? null) ? $details['errorMessages'] : [] as $message) {
                if (is_string($message) && trim($message) !== '') {
                    $messages[] = trim($message);
                }
            }
        }

        $summary = JiraErrorResponse::summary($status, $this->translator);

        return $messages === [] ? $summary : $summary . ' ' . implode(' ', $messages);
    }
}
