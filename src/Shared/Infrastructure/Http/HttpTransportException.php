<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

final class HttpTransportException extends \RuntimeException
{
    private function __construct(
        public readonly string $reason,
        public readonly ?int $status = null,
        public readonly string $responseBody = '',
        /** @var array<string, list<string>> */
        public readonly array $responseHeaders = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($previous?->getMessage() ?? $reason, 0, $previous);
    }

    public static function connectionFailed(\Throwable $previous): self
    {
        return new self('connection_failed', previous: $previous);
    }

    /** @param array<string, list<string>> $responseHeaders */
    public static function unsuccessfulResponse(int $status, string $responseBody, array $responseHeaders = []): self
    {
        return new self('unsuccessful_response', $status, $responseBody, $responseHeaders);
    }

    public static function unexpectedJsonPayload(): self
    {
        return new self('unexpected_json_payload');
    }

    public function retryAfterSeconds(): ?int
    {
        $value = $this->responseHeaders['retry-after'][0] ?? null;

        if (!is_string($value) || !ctype_digit($value)) {
            return null;
        }

        $seconds = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return is_int($seconds) ? $seconds : null;
    }
}
