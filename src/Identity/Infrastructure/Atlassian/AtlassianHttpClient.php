<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Infrastructure\Atlassian;

use App\Kernel\Exception\ApplicationRuntimeException as WorkLogRuntimeException;
use App\Shared\Infrastructure\Http\HttpTransportException;
use App\Shared\Infrastructure\Http\JsonHttpTransport;
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

        $summary = match ($status) {
            400 => $this->translator->trans('jira.error.bad_request'),
            401 => $this->translator->trans('jira.error.unauthorized'),
            403 => $this->translator->trans('jira.error.forbidden'),
            404 => $this->translator->trans('jira.error.not_found'),
            429 => $this->translator->trans('jira.error.rate_limit'),
            default => $this->translator->trans('jira.error.generic', ['{status}' => (string) $status]),
        };

        return $messages === [] ? $summary : $summary . ' ' . implode(' ', $messages);
    }
}
